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
use App\Models\Admin;
use App\Models\Deposit;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Operator approval of a manual deposit (§6.1). Credits treasury:pending ->
 * user:available for the requested amount. Idempotent by deposit:manual:{id}
 * so a double-click never double-credits. On-chain deposits keep their own
 * {@see CreditDepositAction} path.
 */
class CreditManualDepositAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(Deposit $deposit, ?Admin $operator = null): Deposit
    {
        if ($deposit->status === DepositStatus::Credited) {
            return $deposit;
        }

        return DB::transaction(function () use ($deposit, $operator): Deposit {
            $deposit = Deposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($deposit->source !== 'manual') {
                throw new RuntimeException('This is not a manual deposit.');
            }
            if ($deposit->status !== DepositStatus::Detected) {
                throw new RuntimeException('Only a pending manual deposit can be credited.');
            }

            // A manual deposit is off-chain and already settled — the cash is in
            // the company bank/mobile account when the operator approves it — so
            // it lands in treasury:hot (settled), not treasury:pending (which is
            // for crypto awaiting confirmations).
            $treasury = $this->accounts->system(LedgerAccountType::TreasuryHot, $deposit->asset_id);
            $available = $this->accounts->forUser($deposit->user_id, LedgerAccountType::UserAvailable, $deposit->asset_id);

            // Platform deposit fee (admin's cut) → fee:income; user is credited net.
            $fee = PlatformFees::depositFee($deposit->amount);
            $net = (string) BigInteger::of($deposit->amount)->minus($fee);

            $lines = [
                PostingLine::debit($treasury->id, $deposit->asset_id, $deposit->amount),
                PostingLine::credit($available->id, $deposit->asset_id, $net),
            ];
            if (BigInteger::of($fee)->isPositive()) {
                $feeIncome = $this->accounts->system(LedgerAccountType::FeeIncome, $deposit->asset_id);
                $lines[] = PostingLine::credit($feeIncome->id, $deposit->asset_id, $fee);
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'deposit.manual.credit',
                idempotencyKey: "deposit:manual:{$deposit->id}",
                lines: $lines,
                memo: "Manual deposit {$deposit->reference}",
                metadata: ['deposit_id' => $deposit->id, 'operator_id' => $operator?->id, 'fee' => $fee],
            ));

            $deposit->update([
                'status' => DepositStatus::Credited,
                'fee' => $fee,
                'credit_entry_id' => $entry->id,
                'credited_at' => now(),
            ]);

            ActivityLogger::log('deposit.manual.credited', $deposit, ['amount' => $deposit->amount, 'fee' => $fee], actor: $operator);

            DepositCredited::dispatch($deposit->id);

            return $deposit->refresh();
        });
    }

    public function reject(Deposit $deposit, ?Admin $operator = null, ?string $reason = null): Deposit
    {
        if ($deposit->status !== DepositStatus::Detected || $deposit->source !== 'manual') {
            throw new RuntimeException('Only a pending manual deposit can be rejected.');
        }

        $deposit->update(['status' => DepositStatus::Orphaned]);
        ActivityLogger::log('deposit.manual.rejected', $deposit, ['reason' => $reason], actor: $operator);

        return $deposit->refresh();
    }
}
