<?php

declare(strict_types=1);

use App\Domain\Compliance\ComplianceListService;
use App\Domain\P2p\CreateOrderAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\AmlAlert;
use App\Models\P2pAd;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);
    updateSetting('p2p_risk_enabled', true);
    // Neutral defaults; each test tightens the knob it exercises.
    updateSetting('p2p_daily_limit_full', 0);
    updateSetting('p2p_max_orders_per_hour', 0);
    updateSetting('p2p_high_value_usdt', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000');
    $this->ad = P2pAd::factory()->create(['user_id' => $this->seller->id, 'asset_id' => $this->usdt->id]);
});

it('blocks a trade when a party is on the sanctions denylist', function () {
    app(ComplianceListService::class)->add('denylist', 'user', (string) $this->buyer->id);

    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(RuntimeException::class);
});

it('blocks a trade over the per-tier daily volume cap', function () {
    updateSetting('p2p_daily_limit_full', 50); // 50 USDT/day for Full tier

    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(RuntimeException::class);
});

it('blocks a trade over the order velocity cap', function () {
    updateSetting('p2p_max_orders_per_hour', 1);

    app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT')); // first ok

    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(RuntimeException::class);
});

it('allows a high-value trade but raises an AML alert and tags the order', function () {
    updateSetting('p2p_high_value_usdt', 50); // 100 USDT is "high value"

    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));

    expect($order->status->value)->toBe('waiting_payment')
        ->and($order->meta['risk']['level'])->toBe('high') // high_value(40) + fresh account(20)
        ->and(AmlAlert::where('user_id', $this->buyer->id)->where('type', 'p2p_high_risk_trade')->count())->toBe(1);
});
