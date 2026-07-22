<?php

declare(strict_types=1);

namespace App\Domain\Credit;

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\CreditLine;
use App\Models\CreditTransaction;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Accrue interest on the outstanding principal (TDD §F6). Interest is booked as
 * income (credit:accrued_fee receivable ↔ fee:income) and increases the debt.
 */
class AccrueInterestAction
{
    private const SECONDS_PER_YEAR = 31_536_000;

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    /** @param  int|null  $elapsedSeconds  override elapsed time (else since last_accrued_at) */
    public function execute(CreditLine $line, ?int $elapsedSeconds = null): CreditLine
    {
        return DB::transaction(function () use ($line, $elapsedSeconds): CreditLine {
            $line = CreditLine::whereKey($line->id)->lockForUpdate()->firstOrFail();
            $line->loadMissing('principalAsset');

            $principal = BigDecimal::of($line->principal_drawn);
            if ($principal->isZero()) {
                $line->update(['last_accrued_at' => now()]);

                return $line;
            }

            $elapsed = $elapsedSeconds ?? max(0, now()->diffInSeconds($line->last_accrued_at ?? now(), true));
            if ($elapsed <= 0) {
                return $line;
            }

            // interest = principal * apr_bps/10000 * elapsed/year
            $interest = $principal
                ->multipliedBy($line->interest_apr_bps)->dividedBy(10_000, 0, RoundingMode::DOWN)
                ->multipliedBy($elapsed)->dividedBy(self::SECONDS_PER_YEAR, 0, RoundingMode::DOWN)
                ->toBigInteger();

            if ($interest->isZero()) {
                $line->update(['last_accrued_at' => now()]);

                return $line;
            }

            $assetId = $line->principal_asset_id;
            $money = Money::ofBase($interest, $line->principalAsset->decimals, $line->principalAsset->symbol);

            $feeReceivable = $this->accounts->system(LedgerAccountType::CreditAccruedFee, $assetId);
            $income = $this->accounts->system(LedgerAccountType::FeeIncome, $assetId);

            $entry = $this->ledger->post(new EntryData(
                type: 'credit.accrue',
                idempotencyKey: 'credit:accrue:'.$line->id.':'.now()->timestamp,
                lines: [
                    PostingLine::debit($feeReceivable->id, $assetId, $money),
                    PostingLine::credit($income->id, $assetId, $money),
                ],
                memo: 'Interest accrual',
                metadata: ['credit_line_id' => $line->id],
            ));

            $line->accrued_fee = (string) BigInteger::of($line->accrued_fee)->plus($interest);
            $line->last_accrued_at = now();
            $line->save();

            CreditTransaction::create([
                'credit_line_id' => $line->id, 'type' => 'accrue',
                'asset_id' => $assetId, 'amount' => (string) $interest, 'entry_id' => $entry->id,
            ]);

            return $line->refresh();
        });
    }
}
