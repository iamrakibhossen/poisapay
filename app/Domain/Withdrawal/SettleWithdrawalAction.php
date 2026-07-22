<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Enums\OnchainTxStatus;
use App\Enums\WithdrawalStatus;
use App\Events\WithdrawalCompleted;
use App\Models\OnchainTx;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Settle a broadcast withdrawal (TDD §6.3 step 6). Once confirmed on-chain, the
 * reserved funds leave the ledger: debit user:locked (amount+fee), credit
 * treasury:out (amount) and fee:income (fee). Idempotent by withdrawal id.
 */
class SettleWithdrawalAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(Withdrawal $withdrawal, ?string $txHash = null): Withdrawal
    {
        if ($withdrawal->status === WithdrawalStatus::Completed) {
            return $withdrawal;
        }

        return DB::transaction(function () use ($withdrawal, $txHash): Withdrawal {
            $withdrawal = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            $withdrawal->loadMissing('asset');
            $asset = $withdrawal->asset;

            $amount = Money::ofBase($withdrawal->amount, $asset->decimals, $asset->symbol);
            $fee = Money::ofBase($withdrawal->fee, $asset->decimals, $asset->symbol);
            $total = $amount->plus($fee);

            $locked = $this->accounts->forUser($withdrawal->user_id, LedgerAccountType::UserLocked, $asset->id);
            $treasuryOut = $this->accounts->system(LedgerAccountType::TreasuryOut, $asset->id);
            $feeIncome = $this->accounts->system(LedgerAccountType::FeeIncome, $asset->id);

            $lines = [PostingLine::debit($locked->id, $asset->id, $total)];
            $lines[] = PostingLine::credit($treasuryOut->id, $asset->id, $amount);
            if ($fee->isPositive()) {
                $lines[] = PostingLine::credit($feeIncome->id, $asset->id, $fee);
            } else {
                // Keep the entry balanced when there is no fee.
                $lines[0] = PostingLine::debit($locked->id, $asset->id, $amount);
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'withdrawal.settle',
                idempotencyKey: "withdrawal:settle:{$withdrawal->id}",
                lines: $lines,
                memo: 'Withdrawal settled',
                metadata: ['withdrawal_id' => $withdrawal->id],
            ));

            // Record the (simulated) broadcast tx — crypto only. A fiat cash-out
            // (bank / mobile wallet) has no chain, so it settles without an
            // on-chain tx row.
            $onchainId = $withdrawal->onchain_tx_id;
            if ($txHash && ! $onchainId && $asset->chain_id !== null) {
                $tx = OnchainTx::create([
                    'chain_id' => $asset->chain_id,
                    'tx_hash' => $txHash,
                    'log_index' => 0,
                    'to_address' => $withdrawal->to_address,
                    'asset_id' => $asset->id,
                    'amount' => $withdrawal->amount,
                    'confirmations' => $asset->requiredConfirmations(),
                    'status' => OnchainTxStatus::Confirmed,
                    'direction' => 'out',
                ]);
                $onchainId = $tx->id;
            }

            $withdrawal->update([
                'status' => WithdrawalStatus::Completed,
                'settle_entry_id' => $entry->id,
                'onchain_tx_id' => $onchainId,
                'completed_at' => now(),
            ]);

            ActivityLogger::log('withdrawal.settled', $withdrawal);

            WithdrawalCompleted::dispatch($withdrawal->id);

            return $withdrawal->refresh();
        });
    }
}
