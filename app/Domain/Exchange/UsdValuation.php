<?php

declare(strict_types=1);

namespace App\Domain\Exchange;

use App\Domain\Exchange\Contracts\RateProvider;
use App\Models\Asset;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Canonical asset -> USD valuation, shared by every policy that enforces a
 * USD-denominated limit (swap notional, withdrawal tier ceiling, ...). USD and
 * absent-anchor cases short-circuit; everything else is priced through the
 * mid-market {@see RateProvider}. Values are whole-USD (2dp, truncated down).
 */
class UsdValuation
{
    public function __construct(private readonly RateProvider $rates) {}

    /** USD value (major units, 2dp) of an amount of $from. */
    public function toUsd(Asset $from, Money $amount): string
    {
        $decimal = BigDecimal::of($amount->toDecimal());

        if (strtoupper($from->symbol) === 'USD') {
            return (string) $decimal->toScale(2, RoundingMode::DOWN);
        }

        $usd = Asset::where('kind', 'fiat')->where('symbol', 'USD')->first();
        if (! $usd) {
            // No USD anchor configured; value stablecoins ~1:1, otherwise best-effort.
            return (string) $decimal->toScale(2, RoundingMode::DOWN);
        }

        $rate = $this->rates->rate($from, $usd);   // price of 1 `from` in USD

        return (string) $decimal->multipliedBy($rate)->toScale(2, RoundingMode::DOWN);
    }

    /** USD value in minor units (cents) of an amount of $from. */
    public function toUsdMinor(Asset $from, Money $amount): string
    {
        return (string) BigDecimal::of($this->toUsd($from, $amount))
            ->multipliedBy(100)
            ->toScale(0, RoundingMode::DOWN);
    }
}
