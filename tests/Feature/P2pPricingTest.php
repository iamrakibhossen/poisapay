<?php

declare(strict_types=1);

use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\P2pPricingService;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\P2pAd;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000');
    $this->ad = P2pAd::factory()->create(['user_id' => $this->seller->id, 'asset_id' => $this->usdt->id]);
});

it('computes the taker fee from the gross crypto amount', function () {
    updateSetting('p2p_taker_fee_bps', 250); // 2.5%
    $fee = app(P2pPricingService::class)->computeFee(Money::ofDecimal('100', 6, 'USDT'));
    expect($fee->baseString())->toBe('2500000');
});

it('records the fixed price and indicative fiat total on the order', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));

    expect($order->price)->toBe('120.0000')         // fixed unit price
        ->and($order->fiat_amount)->toBe('12000.00'); // 100 × 120
});

it('rejects an order outside the ad fiat limits', function () {
    $ad = P2pAd::factory()->create([
        'user_id' => $this->seller->id,
        'asset_id' => $this->usdt->id,
        'fixed_price' => '120.0000',
        'min_order' => '10000.00',
        'max_order' => '20000.00',
    ]);

    // 50 × 120 = 6000 fiat, below the 10,000 minimum.
    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('50', 6, 'USDT')))
        ->toThrow(RuntimeException::class);
});

it('blocks trading against your own ad', function () {
    expect(fn () => app(CreateOrderAction::class)->execute($this->seller, $this->ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(RuntimeException::class);
});

it('is inert unless the p2p_enabled flag is on', function () {
    updateSetting('p2p_enabled', false);

    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(RuntimeException::class);
});
