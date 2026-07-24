<?php

declare(strict_types=1);

namespace App\Domain\Exchange;

use App\Domain\Compliance\AccountGuard;
use App\Enums\KycTier;
use App\Models\Asset;
use App\Models\Conversion;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;
use RuntimeException;

/**
 * User-facing policy for the SWAP context only — ramp and card-settlement
 * conversions bypass this entirely. Enforces the feature flag, an active
 * account, a minimum KYC tier, and a rolling-24h swap-notional ceiling. All
 * thresholds are admin-configurable settings whose defaults are permissive, so
 * behaviour is unchanged until an operator tightens them.
 */
class SwapPolicy
{
    public function __construct(private readonly UsdValuation $usd) {}

    /** Feature flag + active account + minimum KYC tier. */
    public function assertEligible(User $user): void
    {
        if (! feature('exchange_enabled', true)) {
            throw new RuntimeException('Exchange is currently unavailable.');
        }

        AccountGuard::assertActive($user);

        $min = KycTier::tryFrom((string) getSetting('exchange_min_kyc', config('poisapay.swap_min_kyc', 'unverified')))
            ?? KycTier::Unverified;

        if (! $user->tier()->atLeast($min)) {
            throw new RuntimeException("Swapping requires at least {$min->value} verification.");
        }
    }

    /** Rolling-24h swap notional ceiling in whole USD (0 = unlimited). */
    public function assertWithinDailyLimit(User $user, Asset $from, Money $amount): void
    {
        $limit = (int) getSetting('exchange_daily_limit_usd', config('poisapay.swap_daily_limit_usd', 0));
        if ($limit <= 0) {
            return;
        }

        $incoming = BigDecimal::of($this->notionalUsd($from, $amount));
        $used = BigDecimal::of((string) (Conversion::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->sum('notional_usd') ?: '0'));

        if ($used->plus($incoming)->isGreaterThan(BigDecimal::of($limit))) {
            throw new RuntimeException("This swap would exceed your daily swap limit of {$limit} USD.");
        }
    }

    /** USD value (major units, 2dp) of a from-amount — for limits + the record. */
    public function notionalUsd(Asset $from, Money $amount): string
    {
        return $this->usd->toUsd($from, $amount);
    }
}
