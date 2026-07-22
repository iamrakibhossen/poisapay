<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\RewardCampaign;
use Illuminate\Database\Seeder;

/** Default reward campaigns (§F5) — admin-editable; mirror the legacy config defaults. */
class RewardCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $bdt = Asset::where('currency_code', 'BDT')->first();

        if ($bdt) {
            $fixed = [
                'welcome' => ['Welcome bonus', 5000],
                'referral_referrer' => ['Referral — referrer bonus', 20000],
                'referral_referee' => ['Referral — referee bonus', 10000],
            ];
            foreach ($fixed as $key => [$name, $amount]) {
                RewardCampaign::firstOrCreate(
                    ['key' => $key],
                    ['name' => $name, 'type' => 'fixed', 'asset_id' => $bdt->id, 'amount' => (string) $amount, 'is_active' => true],
                );
            }
        }

        // Spend cashback — 0.5% of settled card spend, paid in the spend asset.
        RewardCampaign::firstOrCreate(
            ['key' => 'cashback'],
            ['name' => 'Card spend cashback', 'type' => 'percentage', 'rate_bps' => 50, 'is_active' => true],
        );
    }
}
