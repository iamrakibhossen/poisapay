<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\P2pAdStatus;
use App\Enums\P2pAdType;
use App\Enums\P2pPriceType;
use App\Models\Asset;
use App\Models\P2pAd;
use App\Models\P2pMerchantProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Populate the P2P marketplace with a handful of merchants running live buy/sell
 * ads, so the browse page isn't empty. The payment-method catalog is seeded by
 * the p2p tables migration, so it's not repeated here. Idempotent — seeds once.
 *
 * The marketplace itself stays behind the `p2p_enabled` flag; this only provides
 * the data an operator sees once they turn it on.
 */
class P2pSeeder extends Seeder
{
    public function run(): void
    {
        if (P2pAd::query()->exists()) {
            return; // already seeded
        }

        $usdt = Asset::where('symbol', 'USDT')->orderBy('id')->first();
        if (! $usdt) {
            return;
        }

        // Merchant personas: name, level, rating, trades, and the ads they run.
        $merchants = [
            ['CryptoBazar BD', 2, '4.95', 1240, [
                ['side' => P2pAdType::Sell, 'price' => '122.5000'],
                ['side' => P2pAdType::Buy, 'margin' => 80],
            ]],
            ['Dhaka Digital', 3, '4.99', 5300, [
                ['side' => P2pAdType::Sell, 'price' => '121.8000'],
                ['side' => P2pAdType::Sell, 'margin' => 50],
            ]],
            ['FastPay Traders', 1, '4.80', 210, [
                ['side' => P2pAdType::Buy, 'price' => '120.5000'],
            ]],
        ];

        foreach ($merchants as $i => [$name, $level, $rating, $trades, $ads]) {
            $user = User::factory()->create(['name' => $name]);

            P2pMerchantProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'is_online' => true,
                    'level' => $level,
                    'badges' => $level >= 2 ? ['verified'] : [],
                    'trade_count' => $trades,
                    'completed_count' => $trades,
                    'completion_rate_bps' => 9800 + $i * 50,
                    'avg_release_seconds' => 90 + $i * 30,
                    'avg_pay_seconds' => 300,
                    'total_volume' => (string) ($trades * 100_000000), // ~trades × 100 USDT (6dp)
                    'rating' => $rating,
                ],
            );

            foreach ($ads as $j => $ad) {
                $floating = isset($ad['margin']);
                P2pAd::factory()
                    ->when($ad['side'] === P2pAdType::Buy, fn ($f) => $f->buy())
                    ->when($floating, fn ($f) => $f->floating($ad['margin']))
                    ->create([
                        'user_id' => $user->id,
                        'asset_id' => $usdt->id,
                        'fiat_currency' => 'BDT',
                        'fixed_price' => $floating ? null : $ad['price'],
                        'price_type' => $floating ? P2pPriceType::Floating : P2pPriceType::Fixed,
                        'min_order' => '500.00',
                        'max_order' => '150000.00',
                        'status' => P2pAdStatus::Active,
                        'priority' => $level,
                    ]);
            }
        }
    }
}
