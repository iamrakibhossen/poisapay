<?php

declare(strict_types=1);

namespace App\Domain\Rewards;

use App\Enums\ReferralStatus;
use App\Models\Asset;
use App\Models\Referral;
use App\Models\RewardCampaign;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Process a referral once the referee performs a qualifying action — KYC
 * completion (TDD §F5). Pays the configured referrer and referee bonuses (BDT
 * paisa) from treasury into each party's available balance and advances the
 * referral to Rewarded. Idempotency keys on the grants guard against
 * double-payout if this runs more than once.
 */
class ProcessReferralQualificationAction
{
    public function __construct(
        private readonly GrantRewardAction $grantReward,
    ) {}

    public function execute(Referral $referral): void
    {
        if ($referral->status !== ReferralStatus::Pending) {
            return;
        }

        $bdt = Asset::where('currency_code', 'BDT')->first();
        if (! $bdt) {
            // No BDT fiat asset provisioned — nothing to pay out; leave pending.
            return;
        }

        DB::transaction(function () use ($referral, $bdt): void {
            $referral->update(['status' => ReferralStatus::Qualified]);

            $referral->loadMissing('referrer', 'referee');

            // Admin campaigns are authoritative; fall back to the legacy config defaults.
            $referrerBonus = $this->bonus('referral_referrer', 'poisapay.rewards.referrer_bonus_bdt', $bdt);
            $refereeBonus = $this->bonus('referral_referee', 'poisapay.rewards.referee_bonus_bdt', $bdt);

            $referrerGrant = $this->grantReward->execute(
                $referral->referrer,
                'referral.referrer',
                $bdt,
                $referrerBonus,
                "reward:referral:{$referral->id}:referrer",
            );

            $this->grantReward->execute(
                $referral->referee,
                'referral.referee',
                $bdt,
                $refereeBonus,
                "reward:referral:{$referral->id}:referee",
            );

            $referral->update([
                'status' => ReferralStatus::Rewarded,
                'reward_entry_id' => $referrerGrant->entry_id,
            ]);
        });
    }

    /** Resolve a fixed-bonus amount from a live campaign, else the config default. */
    private function bonus(string $campaignKey, string $configKey, Asset $fallbackAsset): Money
    {
        $campaign = RewardCampaign::live($campaignKey);
        if ($campaign && ($money = $campaign->fixedMoney())) {
            return $money;
        }

        return Money::ofBase((string) config($configKey), $fallbackAsset->decimals, $fallbackAsset->symbol);
    }
}
