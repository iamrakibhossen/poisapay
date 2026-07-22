<?php

declare(strict_types=1);

namespace App\Domain\Credit;

use App\Domain\Exchange\Contracts\RateProvider;
use App\Models\Asset;
use App\Models\CreditLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Valuation + LTV maths for crypto-backed credit (TDD §F6). Collateral and debt
 * are marked to market via the rate provider; LTV = debt / collateral in bps.
 */
class CreditService
{
    public function __construct(private readonly RateProvider $rates) {}

    /** Outstanding debt = principal drawn + accrued fee (principal asset base units). */
    public function debtBase(CreditLine $line): string
    {
        return (string) BigDecimal::of($line->principal_drawn)->plus($line->accrued_fee)->toBigInteger();
    }

    /** Current loan-to-value in basis points (0 if no collateral). */
    public function currentLtvBps(CreditLine $line): int
    {
        $line->loadMissing('collateralAsset', 'principalAsset');

        $collateralValue = $this->valueInUsd($line->collateralAsset, $line->collateral_amount);
        if ($collateralValue->isZero()) {
            return 0;
        }

        $debtValue = $this->valueInUsd($line->principalAsset, $this->debtBase($line));

        return (int) $debtValue->dividedBy($collateralValue, 6, RoundingMode::HALF_UP)
            ->multipliedBy(10_000)->toScale(0, RoundingMode::HALF_UP)->toInt();
    }

    /** Max additional principal (base units) drawable while staying under max LTV. */
    public function availableToDrawBase(CreditLine $line): string
    {
        $line->loadMissing('collateralAsset', 'principalAsset');

        $collateralUsd = $this->valueInUsd($line->collateralAsset, $line->collateral_amount);
        $maxDebtUsd = $collateralUsd->multipliedBy($line->max_ltv_bps)->dividedBy(10_000, 18, RoundingMode::DOWN);
        $currentDebtUsd = $this->valueInUsd($line->principalAsset, $this->debtBase($line));
        $headroomUsd = $maxDebtUsd->minus($currentDebtUsd);

        if ($headroomUsd->isNegativeOrZero()) {
            return '0';
        }

        // Convert USD headroom back to principal-asset base units.
        $priceUsd = $this->unitPriceUsd($line->principalAsset);
        if ($priceUsd->isZero()) {
            return '0';
        }
        $whole = $headroomUsd->dividedBy($priceUsd, 18, RoundingMode::DOWN);

        return (string) $whole->withPointMovedRight($line->principalAsset->decimals)
            ->toScale(0, RoundingMode::DOWN)->toBigInteger();
    }

    public function needsLiquidation(CreditLine $line): bool
    {
        return $this->currentLtvBps($line) >= $line->liquidation_ltv_bps;
    }

    private function valueInUsd(Asset $asset, string $base): BigDecimal
    {
        $whole = BigDecimal::ofUnscaledValue($base, $asset->decimals);

        return $whole->multipliedBy($this->unitPriceUsd($asset));
    }

    private function unitPriceUsd(Asset $asset): BigDecimal
    {
        $usd = Asset::where('symbol', 'USDT')->first() ?? $asset;

        return $this->rates->rate($asset, $usd);
    }
}
