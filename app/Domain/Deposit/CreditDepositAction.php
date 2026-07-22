<?php

declare(strict_types=1);

namespace App\Domain\Deposit;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Fees\PlatformFees;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\DepositStatus;
use App\Enums\LedgerAccountType;
use App\Events\DepositCredited;
use App\Models\Deposit;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;

/**
 * Credit a confirmed deposit (TDD §6.1 step 6): post treasury:pending ->
 * user:available once min_confirmations is reached and the tx is still
 * canonical. Idempotent by deposit:{txhash}:{logIndex} so reorg re-checks and
 * queue retries never double-credit (D5/D6).
 */
class CreditDepositAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(Deposit $deposit): Deposit
    {
        if ($deposit->status === DepositStatus::Credited) {
            return $deposit; // already credited
        }

        return DB::transaction(function () use ($deposit): Deposit {
            $deposit->loadMissing('onchainTx', 'asset');

            $treasury = $this->accounts->system(LedgerAccountType::TreasuryPending, $deposit->asset_id);
            $available = $this->accounts->forUser($deposit->user_id, LedgerAccountType::UserAvailable, $deposit->asset_id);

            // Platform deposit fee (admin's cut) → fee:income; user is credited net.
            $fee = PlatformFees::depositFee($deposit->amount);
            $net = (string) BigInteger::of($deposit->amount)->minus($fee);

            $idempotencyKey = sprintf(
                'deposit:%s:%d',
                $deposit->onchainTx->tx_hash,
                $deposit->onchainTx->log_index,
            );

            $lines = [
                PostingLine::debit($treasury->id, $deposit->asset_id, $deposit->amount),
                PostingLine::credit($available->id, $deposit->asset_id, $net),
            ];
            if (BigInteger::of($fee)->isPositive()) {
                $feeIncome = $this->accounts->system(LedgerAccountType::FeeIncome, $deposit->asset_id);
                $lines[] = PostingLine::credit($feeIncome->id, $deposit->asset_id, $fee);
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'deposit.credit',
                idempotencyKey: $idempotencyKey,
                lines: $lines,
                memo: "Deposit {$deposit->asset->symbol}",
                metadata: ['deposit_id' => $deposit->id, 'fee' => $fee],
            ));

            $deposit->update([
                'status' => DepositStatus::Credited,
                'fee' => $fee,
                'credit_entry_id' => $entry->id,
                'credited_at' => now(),
            ]);

            ActivityLogger::log('deposit.credited', $deposit, ['amount' => $deposit->amount, 'fee' => $fee], 'Deposit credited');

            DepositCredited::dispatch($deposit->id);

            return $deposit->refresh();
        });
    }
}
