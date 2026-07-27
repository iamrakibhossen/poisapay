<?php

declare(strict_types=1);

namespace App\Domain\Spending;

use App\Domain\Exchange\Contracts\RateProvider;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Domain\Spending\DTO\PlannedLeg;
use App\Domain\Spending\DTO\SpendPlan;
use App\Models\Asset;
use App\Models\TradingPair;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * Walks the resolved priority list and selects exactly which balances to draw —
 * consuming each partially as needed — until the requested settlement amount is
 * covered. Assets that are the settlement coin are spent 1:1; others are sized
 * for auto-conversion using the SAME effective (post-spread) rate the exchange
 * engine will apply, so the delivered settlement always covers the target.
 *
 * Pure sizing only: it reads balances/rates and returns a plan. No money moves.
 */
class SpendAssetSelector
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
        private readonly RateProvider $rates,
    ) {}

    /** @param  Collection<int, Asset>  $candidates */
    public function plan(User $user, Asset $settlement, Money $required, Collection $candidates): SpendPlan
    {
        $settlementCoin = $this->accounts->canonicalAssetId($settlement->id);
        $remaining = $required;
        $legs = [];
        $conversionOutput = $settlement->zero();

        foreach ($candidates as $asset) {
            if (! $remaining->isPositive()) {
                break;
            }

            $available = $this->ledger->availableBalance($user, $asset->id);
            if (! $available->isPositive()) {
                continue;
            }

            // Settlement coin: spent directly (it IS the settlement balance, pooled
            // to the same account) — no conversion, no spread.
            if ($this->accounts->canonicalAssetId($asset->id) === $settlementCoin) {
                $value = $settlement->money($available->baseString());
                $take = $value->isLessThan($remaining) ? $value : $remaining;
                $legs[] = new PlannedLeg($settlement, $take, $take, false);
                $remaining = $remaining->minus($take);

                continue;
            }

            $mid = $this->rates->rate($asset, $settlement); // settlement units per 1 asset unit
            if (! $mid->isPositive()) {
                continue;
            }
            $effective = $this->effectiveRate($asset, $settlement, $mid);
            if (! $effective->isPositive()) {
                continue;
            }

            $value = $this->settlementValueOf($available, $asset, $settlement, $effective);
            if (! $value->isPositive()) {
                continue;
            }

            $target = $value->isLessThan($remaining) ? $value : $remaining;

            $spendBase = $this->assetForSettlement($target, $asset, $settlement, $effective);
            if ($spendBase->isGreaterThan($available->base)) {
                $spendBase = $available->base; // guard rounding; target <= value guarantees it fits
            }
            $spend = $asset->money((string) $spendBase);
            if (! $spend->isPositive()) {
                continue;
            }

            $legs[] = new PlannedLeg($asset, $spend, $target, true);
            $conversionOutput = $conversionOutput->plus($target);
            $remaining = $remaining->minus($target);
        }

        return new SpendPlan($legs, ! $remaining->isPositive(), $conversionOutput);
    }

    /** Post-spread rate, mirroring ExchangeService for a non-Swap context (fee = 0). */
    private function effectiveRate(Asset $from, Asset $to, BigDecimal $mid): BigDecimal
    {
        $pair = TradingPair::for($from->id, $to->id);
        $spreadBps = $pair && $pair->spread_bps !== null
            ? (int) $pair->spread_bps
            : (int) getSetting('exchange_spread_bps', config('poisapay.default_spread_bps', 75));

        return $mid->multipliedBy(BigDecimal::of(10_000 - $spreadBps))->dividedBy(10_000, 18, RoundingMode::DOWN);
    }

    /** Settlement value delivered by converting the whole $available balance (floored). */
    private function settlementValueOf(Money $available, Asset $asset, Asset $settlement, BigDecimal $effective): Money
    {
        $base = BigDecimal::ofUnscaledValue($available->baseString(), $asset->decimals)
            ->multipliedBy($effective)
            ->withPointMovedRight($settlement->decimals)
            ->toScale(0, RoundingMode::DOWN)
            ->toBigInteger();

        return $settlement->money((string) $base);
    }

    /** Asset base units needed to deliver >= $target settlement (rounded UP). */
    private function assetForSettlement(Money $target, Asset $asset, Asset $settlement, BigDecimal $effective): BigInteger
    {
        return BigDecimal::ofUnscaledValue($target->baseString(), $settlement->decimals)
            ->dividedBy($effective, $asset->decimals, RoundingMode::UP)
            ->withPointMovedRight($asset->decimals)
            ->toScale(0, RoundingMode::UP)
            ->toBigInteger();
    }
}
