<?php

declare(strict_types=1);

namespace App\Domain\Exchange;

use App\Domain\Exchange\Contracts\RateProvider;
use App\Models\Asset;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Deterministic reference-rate provider (stand-in for a live feed, §F2.1).
 * Rates are expressed against USD then cross-converted. Values are indicative
 * and are the single place to swap for a real market data source (open question).
 */
class StubRateProvider implements RateProvider
{
    /** Indicative USD price per whole unit of each symbol. */
    private const USD_PRICES = [
        'USDT' => '1.00',
        'USDC' => '1.00',
        'ETH' => '3200.00',
        'BNB' => '580.00',
        'TRX' => '0.13',
        'BTC' => '64000.00',
        'USD' => '1.00',
        'BDT' => '0.0091',   // ~110 BDT per USD
        'EUR' => '1.08',
    ];

    public function rate(Asset $from, Asset $to): BigDecimal
    {
        $fromUsd = BigDecimal::of(self::USD_PRICES[$from->symbol] ?? self::USD_PRICES[$from->currency_code] ?? '0');
        $toUsd = BigDecimal::of(self::USD_PRICES[$to->symbol] ?? self::USD_PRICES[$to->currency_code] ?? '0');

        if ($toUsd->isZero()) {
            return BigDecimal::zero();
        }

        return $fromUsd->dividedBy($toUsd, 18, RoundingMode::DOWN);
    }
}
