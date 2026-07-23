<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\P2pAdStatus;
use App\Enums\P2pAdType;
use App\Enums\P2pPriceType;
use App\Models\P2pAd;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<P2pAd>
 */
class P2pAdFactory extends Factory
{
    protected $model = P2pAd::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'side' => P2pAdType::Sell,
            'asset_id' => 1,
            'fiat_currency' => 'BDT',
            'price_type' => P2pPriceType::Fixed,
            'fixed_price' => '120.0000',
            'margin_bps' => null,
            'min_order' => '10.00',
            'max_order' => '1000000.00',
            'available_amount' => '1000000000',   // 1000 USDT @ 6dp
            'total_amount' => '1000000000',
            'daily_limit' => null,
            'payment_window_min' => 15,
            'status' => P2pAdStatus::Active,
            'priority' => 0,
        ];
    }

    public function buy(): static
    {
        return $this->state(fn () => ['side' => P2pAdType::Buy]);
    }

    public function floating(int $marginBps = 100): static
    {
        return $this->state(fn () => [
            'price_type' => P2pPriceType::Floating,
            'margin_bps' => $marginBps,
            'fixed_price' => null,
        ]);
    }
}
