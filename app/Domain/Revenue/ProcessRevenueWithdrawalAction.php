<?php

declare(strict_types=1);

namespace App\Domain\Revenue;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ledger\ReverseEntryAction;
use App\Enums\LedgerAccountType;
use App\Enums\RevenueWithdrawalStatus;
use App\Jobs\BroadcastRevenueWithdrawalJob;
use App\Models\Admin;
use App\Models\JournalEntry;
use App\Models\RevenueWithdrawal;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drives a revenue withdrawal through its lifecycle. Approval moves the profit
 * out of the fee-income accounts into owner:payout (a real balanced ledger entry)
 * and queues the blockchain broadcast; completion stamps the tx; failure reverses
 * the ledger entry so the revenue returns to the wallet. All steps lock the row.
 */
class ProcessRevenueWithdrawalAction
{
    /** Income accounts drawn from, in order. */
    private const FEE_ACCOUNTS = [
        LedgerAccountType::FxSpreadIncome,
        LedgerAccountType::FeeCard,
        LedgerAccountType::FeeIncome,
    ];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
        private readonly RevenueService $revenue,
        private readonly ReverseEntryAction $reverse,
    ) {}

    /** Pending → Approved: post the ledger move + queue the broadcast. */
    public function approve(RevenueWithdrawal $withdrawal, Admin $approver): RevenueWithdrawal
    {
        $withdrawal = DB::transaction(function () use ($withdrawal, $approver): RevenueWithdrawal {
            $withdrawal = RevenueWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if ($withdrawal->status !== RevenueWithdrawalStatus::Pending) {
                throw new RuntimeException('Only a pending withdrawal can be approved.');
            }

            $asset = $withdrawal->asset;
            $amount = $withdrawal->money();
            if ($amount->isGreaterThanOrEqual($this->revenue->balance($asset)) && ! $amount->equals($this->revenue->balance($asset))) {
                throw new RuntimeException('Insufficient revenue balance to approve this withdrawal.');
            }

            $lines = $this->drawLines($withdrawal, $amount);
            $lines[] = PostingLine::credit($this->accounts->system(LedgerAccountType::OwnerPayout, $asset->id)->id, $asset->id, $amount);

            // Real off-ramp: move the backing crypto out of the treasury pools
            // (pending → hot → cold) into treasury:out before the chain broadcast.
            $lines = array_merge($lines, $this->treasuryOutflowLines($asset->id, $amount, $asset->decimals, $asset->symbol));

            $entry = $this->ledger->post(new EntryData(
                type: 'revenue.withdrawal',
                idempotencyKey: 'revwd:approve:'.$withdrawal->id,
                lines: $lines,
                memo: 'Revenue withdrawal '.$withdrawal->destination_address,
                metadata: ['withdrawal_id' => $withdrawal->id, 'approved_by' => $approver->id],
            ));

            $withdrawal->update([
                'status' => RevenueWithdrawalStatus::Approved,
                'entry_id' => $entry->id,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            ActivityLogger::log('revenue.withdrawal.approved', $withdrawal, ['amount' => $amount->baseString()], actor: $approver);

            return $withdrawal->refresh();
        });

        // Hand off to the queue for the (simulated) blockchain broadcast.
        BroadcastRevenueWithdrawalJob::dispatch($withdrawal->id);

        return $withdrawal;
    }

    /** Broadcasting/Processing → Completed: stamp the on-chain result. */
    public function markCompleted(RevenueWithdrawal $withdrawal, string $txHash, ?Money $gas = null): RevenueWithdrawal
    {
        return DB::transaction(function () use ($withdrawal, $txHash, $gas): RevenueWithdrawal {
            $withdrawal = RevenueWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if (! $withdrawal->status->isActive()) {
                return $withdrawal;
            }

            $withdrawal->update([
                'status' => RevenueWithdrawalStatus::Completed,
                'tx_hash' => $txHash,
                'gas_fee' => $gas?->baseString() ?? $withdrawal->gas_fee,
                'completed_at' => now(),
            ]);

            ActivityLogger::log('revenue.withdrawal.completed', $withdrawal, ['tx_hash' => $txHash]);

            return $withdrawal->refresh();
        });
    }

    /** Active → Failed: reverse the ledger entry so the revenue returns to the wallet. */
    public function markFailed(RevenueWithdrawal $withdrawal, string $reason): RevenueWithdrawal
    {
        return DB::transaction(function () use ($withdrawal, $reason): RevenueWithdrawal {
            $withdrawal = RevenueWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if ($withdrawal->status->isTerminal()) {
                return $withdrawal;
            }

            $reversalId = null;
            if ($withdrawal->entry_id) {
                $original = JournalEntry::find($withdrawal->entry_id);
                if ($original) {
                    $reversalId = $this->reverse->execute($original, 'Revenue withdrawal failed')->id;
                }
            }

            $withdrawal->update([
                'status' => RevenueWithdrawalStatus::Failed,
                'failure_reason' => $reason,
                'reversal_entry_id' => $reversalId,
            ]);

            ActivityLogger::log('revenue.withdrawal.failed', $withdrawal, ['reason' => $reason]);

            return $withdrawal->refresh();
        });
    }

    public function setStatus(RevenueWithdrawal $withdrawal, RevenueWithdrawalStatus $status): void
    {
        RevenueWithdrawal::whereKey($withdrawal->id)->update(['status' => $status->value]);
    }

    /** Build debit lines that draw the amount across the fee-income accounts. */
    private function drawLines(RevenueWithdrawal $withdrawal, Money $amount): array
    {
        $assetId = $withdrawal->asset_id;
        $remaining = BigInteger::of($amount->baseString());
        $lines = [];

        foreach (self::FEE_ACCOUNTS as $type) {
            if ($remaining->isZero()) {
                break;
            }
            $account = $this->accounts->system($type, $assetId);
            $bal = BigInteger::of((string) (DB::table('account_balances')->where('account_id', $account->id)->value('balance') ?? '0'));
            if ($bal->isLessThanOrEqualTo(0)) {
                continue;
            }
            $draw = $bal->isLessThan($remaining) ? $bal : $remaining;
            $lines[] = PostingLine::debit($account->id, $assetId, Money::ofBase($draw, $withdrawal->asset->decimals, $withdrawal->asset->symbol));
            $remaining = $remaining->minus($draw);
        }

        if ($remaining->isPositive()) {
            throw new RuntimeException('Revenue balance changed — cannot cover this withdrawal.');
        }

        return $lines;
    }

    /**
     * Ledger legs that send `$amount` out of the treasury: credit the custody
     * pools (pending → hot → cold) until covered and debit treasury:out — the
     * same outflow account user withdrawals settle to. On failure the whole entry
     * is reversed, so the reserve returns.
     *
     * @return array<int, PostingLine>
     */
    private function treasuryOutflowLines(int $assetId, Money $amount, int $decimals, string $symbol): array
    {
        $sources = [
            LedgerAccountType::TreasuryPending,
            LedgerAccountType::TreasuryHot,
            LedgerAccountType::TreasuryCold,
        ];

        $remaining = BigInteger::of($amount->baseString());
        $lines = [];

        foreach ($sources as $type) {
            if ($remaining->isZero()) {
                break;
            }
            $account = $this->accounts->system($type, $assetId);
            $bal = BigInteger::of((string) (DB::table('account_balances')->where('account_id', $account->id)->value('balance') ?? '0'));
            if ($bal->isLessThanOrEqualTo(0)) {
                continue;
            }
            $draw = $bal->isLessThan($remaining) ? $bal : $remaining;
            $lines[] = PostingLine::credit($account->id, $assetId, Money::ofBase($draw, $decimals, $symbol));
            $remaining = $remaining->minus($draw);
        }

        if ($remaining->isPositive()) {
            throw new RuntimeException('Treasury reserves are insufficient to send this withdrawal on-chain.');
        }

        $lines[] = PostingLine::debit($this->accounts->system(LedgerAccountType::TreasuryOut, $assetId)->id, $assetId, $amount);

        return $lines;
    }
}
