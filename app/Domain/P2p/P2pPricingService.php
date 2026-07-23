<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Domain\Exchange\Contracts\RateProvider;
use App\Enums\P2pPriceType;
use App\Models\Asset;
use App\Models\P2pAd;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Throwable;

/**
 * P2P pricing maths — pure, no state mutation. Resolves an ad's fiat unit price
 * (fixed, or floating = live reference × margin) and computes the platform taker
 * fee taken from the escrowed crypto. Fiat figures are indicative (fiat settles
 * off-platform); only the crypto amounts feed the ledger.
 */
class P2pPricingService
{
    public function __construct(private readonly RateProvider $rates) {}

    /** Fiat price for 1 whole crypto unit. */
    public function unitPrice(P2pAd $ad): BigDecimal
    {
        if ($ad->price_type === P2pPriceType::Floating) {
            $reference = $this->referenceUnitPrice($ad);
            if ($reference !== null) {
                $factor = BigDecimal::of('1')->plus(
                    BigDecimal::of((string) ($ad->margin_bps ?? 0))->dividedBy('10000', 8, RoundingMode::HALF_UP)
                );

                return $reference->multipliedBy($factor);
            }
        }

        return BigDecimal::of((string) ($ad->fixed_price ?? '0'));
    }

    /** Live fiat-per-crypto reference from the rate provider, or null if unavailable. */
    public function referenceUnitPrice(P2pAd $ad): ?BigDecimal
    {
        $fiat = Asset::where('symbol', $ad->fiat_currency)->first();
        $asset = $ad->asset;

        if (! $fiat || ! $asset) {
            return null;
        }

        try {
            return BigDecimal::of((string) $this->rates->rate($asset, $fiat));
        } catch (Throwable) {
            return null;
        }
    }

    /** Configured taker fee in basis points (settings override config default). */
    public function feeBps(): int
    {
        return (int) getSetting('p2p_taker_fee_bps', (int) config('p2p.taker_fee_bps', 0));
    }

    /** Taker fee deducted from the gross escrowed crypto (rounded down). */
    public function computeFee(Money $gross, ?int $bps = null): Money
    {
        $bps ??= $this->feeBps();

        if ($bps <= 0) {
            return Money::zero($gross->decimals, $gross->symbol);
        }

        $feeBase = $gross->base->multipliedBy($bps)->dividedBy(10_000, RoundingMode::DOWN);

        return Money::ofBase($feeBase, $gross->decimals, $gross->symbol);
    }

    /** Indicative fiat total for a crypto amount (2dp). */
    public function fiatFor(P2pAd $ad, Money $crypto): string
    {
        $qty = BigDecimal::of($crypto->toDecimal());

        return (string) $this->unitPrice($ad)->multipliedBy($qty)->toScale(2, RoundingMode::HALF_UP);
    }
}
