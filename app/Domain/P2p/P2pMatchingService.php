<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Enums\P2pAdStatus;
use App\Enums\P2pAdType;
use App\Models\P2pAd;
use App\Models\P2pOrder;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * P2P auto-matching engine — the order-book selector for escrow trades.
 *
 * Given a taker's request (which ad side to hit, an amount, and a payment method),
 * it ranks every eligible maker ad by **price-time priority** (best price first,
 * then reputation, then oldest) and opens an escrow order against the best one —
 * falling through to the next candidate if a guard rejects it (limits, trade
 * hours, escrow funds, risk). Settlement still runs the normal escrow pay→confirm
 * →release path: the fiat leg is off-platform and can't be auto-settled, so the
 * engine automates discovery + order creation, not the human payment step.
 *
 * The authoritative money gate stays {@see CreateOrderAction}; this only chooses
 * *which* ad to call it with, so no new money path is introduced.
 */
class P2pMatchingService
{
    /** How many ranked candidates to attempt before giving up. */
    private const MAX_ATTEMPTS = 15;

    public function __construct(
        private readonly CreateOrderAction $createOrder,
        private readonly P2pPricingService $pricing,
    ) {}

    /**
     * Match + open an order for the best-priced eligible ad, or throw when nothing fits.
     *
     * @param  P2pAdType  $adSide  the ad side to trade against (Sell → the taker buys crypto; Buy → the taker sells)
     * @param  string  $amount  crypto amount in the asset's units (decimal string)
     */
    public function match(User $taker, P2pAdType $adSide, string $amount, string $paymentMethodId): P2pOrder
    {
        $lastError = null;

        foreach ($this->rankedCandidates($taker, $adSide, $amount, $paymentMethodId) as $ad) {
            try {
                return $this->createOrder->execute(
                    $taker,
                    $ad,
                    Money::ofDecimal($amount, $ad->asset->decimals, $ad->asset->symbol),
                    $paymentMethodId,
                );
            } catch (Throwable $e) {
                // This ad rejected the order (limits / hours / escrow / risk / block).
                // Try the next best price rather than failing the whole request.
                $lastError = $e;
            }
        }

        throw new NoMatchException($lastError?->getMessage()
            ?: 'No available offer matched your order right now. Try a different amount or payment method.');
    }

    /**
     * Eligible maker ads for this request, ranked by price-time priority:
     * best price first, then completion rate, then online, then oldest ad.
     *
     * @return list<P2pAd>
     */
    private function rankedCandidates(User $taker, P2pAdType $adSide, string $amount, string $paymentMethodId): array
    {
        $uid = (string) $taker->getKey();

        $ads = P2pAd::query()
            ->with(['user', 'asset', 'paymentMethods'])
            ->select('p2p_ads.*')
            ->leftJoin('p2p_merchant_profiles as pr', 'pr.user_id', '=', 'p2p_ads.user_id')
            ->where('p2p_ads.side', $adSide->value)
            ->where('p2p_ads.status', P2pAdStatus::Active->value)
            ->where('p2p_ads.user_id', '!=', $uid)
            ->where('p2p_ads.available_amount', '>=', $amount)
            ->where('p2p_ads.min_order', '<=', $amount)
            ->where('p2p_ads.max_order', '>=', $amount)
            // The taker's chosen payment method must be one the ad accepts.
            ->whereHas('paymentMethods', fn ($m) => $m->where('p2p_payment_methods.id', $paymentMethodId))
            // Merchant not on vacation.
            ->where(fn ($w) => $w->whereNull('pr.vacation_mode')->orWhere(DB::raw('pr.vacation_mode'), false))
            // Neither party has blocked the other.
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('p2p_blocks')->where(fn ($w) => $w
                ->where(fn ($x) => $x->where('user_id', $uid)->whereColumn('blocked_id', 'p2p_ads.user_id'))
                ->orWhere(fn ($x) => $x->where('blocked_id', $uid)->whereColumn('user_id', 'p2p_ads.user_id'))))
            // Coarse pre-rank in SQL; the fine price sort (floating ads) happens in PHP.
            ->orderByRaw('pr.completion_rate_bps desc nulls last')
            ->orderByRaw('pr.is_online desc nulls last')
            ->orderBy('p2p_ads.created_at')
            ->limit(self::MAX_ATTEMPTS)
            ->get()
            ->all();

        // Best price first: buyers (Sell ads) want the lowest unit price; sellers
        // (Buy ads) want the highest. Stable sort preserves the SQL tiebreakers.
        usort($ads, function (P2pAd $a, P2pAd $b) use ($adSide): int {
            $cmp = $this->pricing->unitPrice($a)->compareTo($this->pricing->unitPrice($b));

            return $adSide === P2pAdType::Sell ? $cmp : -$cmp;
        });

        return $ads;
    }

    /**
     * Best available unit price for a side + amount + method, for a live quote —
     * null when nothing matches. Fiat-per-unit as a plain decimal string.
     */
    public function bestPrice(User $taker, P2pAdType $adSide, string $amount, string $paymentMethodId): ?string
    {
        $ads = $this->rankedCandidates($taker, $adSide, $amount, $paymentMethodId);

        return $ads === [] ? null : (string) $this->pricing->unitPrice($ads[0]);
    }
}
