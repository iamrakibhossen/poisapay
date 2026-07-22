<?php

declare(strict_types=1);

namespace App\Domain\Credit;

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\CreditStatus;
use App\Enums\LedgerAccountType;
use App\Models\CreditLine;
use App\Models\CreditTransaction;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Repay principal + accrued fee (TDD §F6). Fee is settled first, then principal.
 * When the debt reaches zero, the pledged collateral is released back to the
 * user's available balance and the line is closed.
 */
class RepayCreditAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(CreditLine $line, Money $amount): CreditLine
    {
        return DB::transaction(function () use ($line, $amount): CreditLine {
            $line = CreditLine::whereKey($line->id)->lockForUpdate()->firstOrFail();
            $line->loadMissing('principalAsset', 'collateralAsset');
            $assetId = $line->principal_asset_id;
            $decimals = $line->principalAsset->decimals;
            $symbol = $line->principalAsset->symbol;

            $debt = BigInteger::of($line->principal_drawn)->plus($line->accrued_fee);
            $pay = BigInteger::min($amount->base, $debt);
            if ($pay->isLessThanOrEqualTo(0)) {
                throw new RuntimeException('Nothing to repay.');
            }

            // Guard balance.
            $available = $this->accounts->forUser($line->user_id, LedgerAccountType::UserAvailable, $assetId);
            $row = DB::table('account_balances')->where('account_id', $available->id)->lockForUpdate()->first();
            if (BigInteger::of($row->balance ?? '0')->isLessThan($pay)) {
                throw new RuntimeException('Insufficient balance to repay.');
            }

            // Apply to fee first, then principal.
            $feePay = BigInteger::min($pay, BigInteger::of($line->accrued_fee));
            $principalPay = $pay->minus($feePay);

            $lines = [PostingLine::debit($available->id, $assetId, Money::ofBase($pay, $decimals, $symbol))];
            if ($feePay->isGreaterThan(0)) {
                $feeAcct = $this->accounts->system(LedgerAccountType::CreditAccruedFee, $assetId);
                $lines[] = PostingLine::credit($feeAcct->id, $assetId, Money::ofBase($feePay, $decimals, $symbol));
            }
            if ($principalPay->isGreaterThan(0)) {
                $principalAcct = $this->accounts->system(LedgerAccountType::CreditPrincipal, $assetId);
                $lines[] = PostingLine::credit($principalAcct->id, $assetId, Money::ofBase($principalPay, $decimals, $symbol));
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'credit.repay',
                idempotencyKey: 'credit:repay:'.$line->id.':'.$debt,
                lines: $lines,
                memo: 'Credit repayment',
                metadata: ['credit_line_id' => $line->id],
            ));

            $line->accrued_fee = (string) BigInteger::of($line->accrued_fee)->minus($feePay);
            $line->principal_drawn = (string) BigInteger::of($line->principal_drawn)->minus($principalPay);

            CreditTransaction::create([
                'credit_line_id' => $line->id, 'type' => 'repay',
                'asset_id' => $assetId, 'amount' => (string) $pay, 'entry_id' => $entry->id,
            ]);

            // Fully repaid → release collateral and close.
            if (BigInteger::of($line->principal_drawn)->plus($line->accrued_fee)->isZero()) {
                $this->releaseCollateral($line);
                $line->status = CreditStatus::Repaid;
            }
            $line->save();

            return $line->refresh();
        });
    }

    private function releaseCollateral(CreditLine $line): void
    {
        if (BigInteger::of($line->collateral_amount)->isZero()) {
            return;
        }

        $locked = $this->accounts->forUser($line->user_id, LedgerAccountType::UserCollateralLocked, $line->collateral_asset_id);
        $available = $this->accounts->forUser($line->user_id, LedgerAccountType::UserAvailable, $line->collateral_asset_id);
        $amount = Money::ofBase($line->collateral_amount, $line->collateralAsset->decimals, $line->collateralAsset->symbol);

        $this->ledger->post(new EntryData(
            type: 'credit.collateral.release',
            idempotencyKey: "credit:release:{$line->id}",
            lines: [
                PostingLine::debit($locked->id, $line->collateral_asset_id, $amount),
                PostingLine::credit($available->id, $line->collateral_asset_id, $amount),
            ],
            memo: 'Collateral released',
            metadata: ['credit_line_id' => $line->id],
        ));

        $line->collateral_amount = '0';
    }
}
