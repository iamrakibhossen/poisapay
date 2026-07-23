<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Domain\Compliance\ComplianceListService;
use App\Domain\Risk\RiskAssessment;
use App\Enums\KycTier;
use App\Enums\RiskLevel;
use App\Models\P2pAd;
use App\Models\P2pOrder;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigInteger;
use RuntimeException;

/**
 * Pre-trade risk assessment for P2P orders. HARD checks throw (sanctions
 * denylist, per-tier daily volume cap, order velocity) and stop the trade
 * before any escrow is locked. SOFT signals accumulate a score and are returned
 * for the caller to raise an AML alert on (high value, fresh account, repeated
 * counterparty, high-risk country). Reuses the platform's {@see RiskAssessment}
 * / {@see RiskLevel} and {@see ComplianceListService}.
 */
class P2pRiskEngine
{
    public function __construct(private readonly ComplianceListService $lists) {}

    public function assess(User $taker, User $counterparty, P2pAd $ad, Money $crypto): RiskAssessment
    {
        // ── Hard: sanctions / denylist on either party ──
        foreach ([$taker, $counterparty] as $party) {
            if ($this->lists->isDenied('user', (string) $party->getKey())
                || ($party->email && $this->lists->isDenied('email', $party->email))) {
                throw new RuntimeException('This trade is blocked by compliance.');
            }
        }

        if (! feature('p2p_risk_enabled', true)) {
            return new RiskAssessment(0, RiskLevel::Low, []);
        }

        // ── Hard: per-tier daily traded volume ──
        $cap = $this->dailyVolumeCapBase($taker, $crypto);
        if ($cap !== null) {
            $today = $this->dailyVolumeBase($taker);
            if ($today->plus($crypto->base)->isGreaterThan($cap)) {
                throw new RuntimeException('Daily P2P trading limit reached for your verification level.');
            }
        }

        // ── Hard: order velocity ──
        $perHour = (int) getSetting('p2p_max_orders_per_hour', 20);
        if ($perHour > 0 && $this->recentOrderCount($taker, now()->subHour()) >= $perHour) {
            throw new RuntimeException('You are opening orders too quickly — please wait a moment.');
        }

        // ── Soft signals ──
        $score = 0;
        $reasons = [];

        $highValue = (int) getSetting('p2p_high_value_usdt', 5000);
        if ($highValue > 0
            && $crypto->isGreaterThanOrEqual(Money::ofDecimal((string) $highValue, $crypto->decimals, $crypto->symbol))) {
            $score += 40;
            $reasons[] = 'high_value_trade';
        }

        if ($taker->created_at && $taker->created_at->gt(now()->subDay())) {
            $score += 20;
            $reasons[] = 'new_account';
        }

        if ($this->counterpartyTradeCount($taker, $counterparty) >= 5) {
            $score += 20;
            $reasons[] = 'repeated_counterparty';
        }

        if ($this->lists->countryRisk($taker->country ?? null) === 'high') {
            $score += 25;
            $reasons[] = 'high_risk_country';
        }

        $score = min($score, 100);

        return new RiskAssessment($score, RiskLevel::fromScore($score), $reasons);
    }

    private function dailyVolumeCapBase(User $user, Money $crypto): ?BigInteger
    {
        $whole = match ($user->tier()) {
            KycTier::Full => (int) getSetting('p2p_daily_limit_full', 25000),
            KycTier::Basic => (int) getSetting('p2p_daily_limit_basic', 1000),
            default => 0,
        };

        return $whole > 0
            ? Money::ofDecimal((string) $whole, $crypto->decimals, $crypto->symbol)->base
            : null;
    }

    private function dailyVolumeBase(User $user): BigInteger
    {
        return P2pOrder::query()
            ->where(fn ($q) => $q->where('buyer_id', $user->getKey())->orWhere('seller_id', $user->getKey()))
            ->whereNotIn('status', ['cancelled', 'expired', 'force_cancelled'])
            ->where('created_at', '>=', now()->startOfDay())
            ->pluck('crypto_amount')
            ->reduce(fn (BigInteger $carry, $amount) => $carry->plus(BigInteger::of((string) $amount)), BigInteger::zero());
    }

    private function recentOrderCount(User $user, \DateTimeInterface $since): int
    {
        return P2pOrder::query()
            ->where(fn ($q) => $q->where('buyer_id', $user->getKey())->orWhere('seller_id', $user->getKey()))
            ->where('created_at', '>=', $since)
            ->count();
    }

    private function counterpartyTradeCount(User $a, User $b): int
    {
        return P2pOrder::query()
            ->where(function ($q) use ($a, $b) {
                $q->where(fn ($x) => $x->where('buyer_id', $a->getKey())->where('seller_id', $b->getKey()))
                    ->orWhere(fn ($x) => $x->where('buyer_id', $b->getKey())->where('seller_id', $a->getKey()));
            })
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }
}
