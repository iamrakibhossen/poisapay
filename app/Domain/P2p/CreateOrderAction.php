<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Auth\DeviceService;
use App\Domain\Compliance\AccountGuard;
use App\Domain\Compliance\RaiseAlertAction;
use App\Domain\Risk\RiskAssessment;
use App\Enums\KycTier;
use App\Enums\P2pAdStatus;
use App\Enums\P2pAdType;
use App\Enums\P2pOrderStatus;
use App\Enums\RiskLevel;
use App\Events\P2pOrderCreated;
use App\Jobs\P2pExpireOrderJob;
use App\Models\P2pAd;
use App\Models\P2pBlock;
use App\Models\P2pOrder;
use App\Models\User;
use App\Notifications\LedgerEventNotification;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Open a P2P order against an ad. Guards (flag, account status, KYC, self-trade,
 * inventory, fiat limits) run first; then, under a row lock on the ad, inventory
 * is decremented, the order is created, and the seller's USDT is escrowed
 * atomically — if escrow fails (insufficient funds) the whole thing rolls back.
 */
class CreateOrderAction
{
    public function __construct(
        private readonly PlaceEscrowAction $placeEscrow,
        private readonly P2pPricingService $pricing,
        private readonly P2pOrderService $orders,
        private readonly P2pRiskEngine $risk,
        private readonly AdOrderGuard $guard,
        private readonly RaiseAlertAction $alerts,
    ) {}

    public function execute(User $taker, P2pAd $ad, Money $cryptoAmount, ?string $paymentMethodId = null, ?string $ip = null, ?string $fingerprint = null): P2pOrder
    {
        if (! feature('p2p_enabled', false)) {
            throw new RuntimeException('P2P marketplace is not enabled.');
        }

        // Network fingerprint for fraud analysis (falls back to the live request).
        $ip ??= request()->ip();
        $fingerprint ??= DeviceService::fingerprint(request());

        $ad->loadMissing(['asset', 'user']);
        $advertiser = $ad->user;

        if ($advertiser->getKey() === $taker->getKey()) {
            throw new RuntimeException('You cannot trade against your own ad.');
        }
        if (P2pBlock::existsBetween((string) $taker->getKey(), (string) $advertiser->getKey())) {
            throw new RuntimeException('You cannot trade with this merchant.');
        }
        if (! $ad->status->isMatchable()) {
            throw new RuntimeException('This ad is not available for trading.');
        }
        if (! $cryptoAmount->isPositive()) {
            throw new RuntimeException('Order amount must be positive.');
        }

        AccountGuard::assertActive($taker);
        AccountGuard::assertActive($advertiser);

        $required = feature('p2p_require_full_kyc', false) ? KycTier::Full : KycTier::Basic;
        if (! $taker->tier()->atLeast($required) || ! $advertiser->tier()->atLeast($required)) {
            throw new RuntimeException('Both parties must complete the required verification to trade.');
        }

        // Risk & compliance (hard checks throw before any escrow is locked).
        $assessment = $this->risk->assess($taker, $advertiser, $ad, $cryptoAmount, $fingerprint);

        // Critical risk can auto-freeze the taker (default-off flag). A frozen
        // account is blocked from all value movement by AccountGuard thereafter.
        if ($assessment->level === RiskLevel::Critical && feature('p2p_auto_freeze', false)) {
            $taker->update(['is_frozen' => true]);
            $this->alerts->execute($taker, 'p2p_auto_freeze', $assessment->level, $assessment->score, $assessment->reasons, 'p2p');
            ActivityLogger::log('p2p.auto_freeze', $taker, ['score' => $assessment->score, 'reasons' => $assessment->reasons]);

            throw new RuntimeException('Your account is under review — P2P trading is paused. Please contact support.');
        }

        // Advertiser-configured trading constraints (hours, requirement, country).
        $this->guard->assertOrderable($ad, $taker);

        // The seller's crypto is escrowed. A sell ad's advertiser is the seller;
        // a buy ad's advertiser is the buyer (so the taker sells).
        [$sellerId, $buyerId] = $ad->side === P2pAdType::Sell
            ? [$advertiser->getKey(), $taker->getKey()]
            : [$taker->getKey(), $advertiser->getKey()];

        // Pricing + fiat limits (fiat is indicative; only crypto touches the ledger).
        $feeBps = $this->pricing->feeBps();
        $fee = $this->pricing->computeFee($cryptoAmount, $feeBps);
        $net = $cryptoAmount->minus($fee);
        $unitPrice = $this->pricing->unitPrice($ad);
        $fiat = BigDecimal::of($cryptoAmount->toDecimal())->multipliedBy($unitPrice)->toScale(2, RoundingMode::HALF_UP);

        $min = BigDecimal::of((string) $ad->min_order);
        $max = BigDecimal::of((string) $ad->max_order);
        if ($fiat->isLessThan($min) || $fiat->isGreaterThan($max)) {
            throw new RuntimeException("Order total {$fiat} {$ad->fiat_currency} is outside the ad limits ({$min}–{$max}).");
        }

        try {
            $order = $this->openOrder($ad, $sellerId, $buyerId, $cryptoAmount, $fee, $net, $feeBps, $fiat, $unitPrice, $paymentMethodId, $assessment, $ip, $fingerprint);
        } catch (InsufficientEscrowFundsException $e) {
            // The order rolled back. If the funding party is the ad owner, the ad
            // is under-funded — pause it so buyers stop hitting escrow failures.
            if ($ad->user_id === $sellerId) {
                $this->autoPauseAd($ad, $advertiser);
            }
            throw $e;
        }

        P2pOrderCreated::dispatch($order->id);
        P2pExpireOrderJob::dispatch($order->id)->delay($order->expires_at);

        // Flagged (non-low) trades raise an AML alert onto the taker's compliance case.
        if ($assessment->level !== RiskLevel::Low) {
            $this->alerts->execute($taker, 'p2p_high_risk_trade', $assessment->level, $assessment->score, $assessment->reasons, 'p2p', $order);
        }

        return $order;
    }

    private function openOrder(P2pAd $ad, string $sellerId, string $buyerId, Money $cryptoAmount, Money $fee, Money $net, int $feeBps, BigDecimal $fiat, BigDecimal $unitPrice, ?string $paymentMethodId, RiskAssessment $assessment, ?string $ip, ?string $fingerprint): P2pOrder
    {
        return DB::transaction(function () use ($ad, $sellerId, $buyerId, $cryptoAmount, $fee, $net, $feeBps, $fiat, $unitPrice, $paymentMethodId, $assessment, $ip, $fingerprint): P2pOrder {
            // Lock the ad row and re-check inventory to serialise concurrent orders.
            $lockedAd = P2pAd::where('id', $ad->id)->lockForUpdate()->firstOrFail();
            $available = Money::ofBase($lockedAd->available_amount, $cryptoAmount->decimals, $cryptoAmount->symbol);
            if ($available->isLessThan($cryptoAmount)) {
                throw new RuntimeException('Not enough remaining on this ad.');
            }

            // Per-ad daily cap — serialised with the ad lock above.
            $this->guard->assertWithinAdDailyLimit($lockedAd, $cryptoAmount);

            $lockedAd->update(['available_amount' => $available->minus($cryptoAmount)->baseString()]);

            $order = P2pOrder::create([
                'ref' => $this->orders->generateRef(),
                'ad_id' => $ad->id,
                'buyer_id' => $buyerId,
                'seller_id' => $sellerId,
                'asset_id' => $ad->asset_id,
                'crypto_amount' => $cryptoAmount->baseString(),
                'fee_amount' => $fee->baseString(),
                'net_amount' => $net->baseString(),
                'taker_fee_bps' => $feeBps,
                'fiat_amount' => (string) $fiat,
                'price' => (string) $unitPrice->toScale(4, RoundingMode::HALF_UP),
                'fiat_currency' => $ad->fiat_currency,
                'payment_method_id' => $paymentMethodId,
                'status' => P2pOrderStatus::WaitingPayment,
                'expires_at' => Carbon::now()->addMinutes((int) $lockedAd->payment_window_min),
                'meta' => $assessment->score > 0
                    ? ['risk' => ['score' => $assessment->score, 'level' => $assessment->level->value, 'reasons' => $assessment->reasons]]
                    : null,
                'taker_ip' => $ip,
                'taker_fingerprint' => $fingerprint,
            ]);

            // Lock the seller's USDT (rolls back the order + inventory if funds are short).
            $this->placeEscrow->execute($order);

            $this->orders->recordEvent($order, null, P2pOrderStatus::WaitingPayment->value, 'system', null, 'order opened');

            return $order;
        });
    }

    /** Pause an under-funded ad and alert its owner to top up. Best-effort, out of band. */
    private function autoPauseAd(P2pAd $ad, User $advertiser): void
    {
        $fresh = P2pAd::find($ad->id);
        if (! $fresh || $fresh->status !== P2pAdStatus::Active) {
            return;
        }

        $fresh->update(['status' => P2pAdStatus::Paused]);
        ActivityLogger::log('p2p.ad.auto_paused', $fresh, ['reason' => 'insufficient_balance']);

        $advertiser->notify(new LedgerEventNotification(
            __('Ad paused — top up to resume'),
            __('Your P2P ad was paused because your available balance could not cover a new order’s escrow. Add funds, then resume it from My Ads.'),
            'p2p.ad.auto_paused',
            route('p2p.ads'),
        ));
    }
}
