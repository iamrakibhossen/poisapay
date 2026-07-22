<?php

declare(strict_types=1);

namespace App\Domain\Rewards;

use App\Models\Asset;
use App\Models\RewardCampaign;
use App\Models\RewardGrant;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigInteger;

/**
 * Award spend cashback (TDD §F5). Driven by the admin-configured `cashback`
 * campaign: a percentage of the qualifying spend, gated by a minimum and capped
 * by a maximum, paid in the spend asset. A no-op (returns null) when no live
 * campaign applies. Idempotent via the caller's key (one cashback per event).
 */
class AwardCashbackAction
{
    public function __construct(
        private readonly GrantRewardAction $grant,
    ) {}

    public function execute(User $user, Asset $spendAsset, Money $spendAmount, string $idempotencyKey): ?RewardGrant
    {
        $campaign = RewardCampaign::live('cashback');
        if (! $campaign || $campaign->type !== 'percentage' || ! $campaign->rate_bps) {
            return null;
        }

        // Minimum spend gate (interpreted in the spend asset's base units).
        if ($campaign->min_spend !== null) {
            $min = Money::ofBase($campaign->min_spend, $spendAsset->decimals, $spendAsset->symbol);
            if ($spendAmount->isLessThan($min)) {
                return null;
            }
        }

        $cashback = Money::ofBase(
            BigInteger::of($spendAmount->baseString())->multipliedBy($campaign->rate_bps)->dividedBy(10_000),
            $spendAsset->decimals,
            $spendAsset->symbol,
        );

        // Per-event cap.
        if ($campaign->max_reward !== null) {
            $cap = Money::ofBase($campaign->max_reward, $spendAsset->decimals, $spendAsset->symbol);
            if ($cashback->isGreaterThanOrEqual($cap) && ! $cashback->equals($cap)) {
                $cashback = $cap;
            }
        }

        if (! $cashback->isPositive()) {
            return null;
        }

        return $this->grant->execute($user, 'cashback', $spendAsset, $cashback, $idempotencyKey);
    }
}
