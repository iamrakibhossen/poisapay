<?php

declare(strict_types=1);

use App\Domain\P2p\CreateOrderAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\P2pAd;
use App\Models\User;
use App\Models\UserDevice;
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

it('captures the taker IP and fingerprint on the order', function () {
    $order = app(CreateOrderAction::class)->execute(
        $this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'),
        ip: '203.0.113.9', fingerprint: 'fp-unique-1',
    );

    expect($order->taker_ip)->toBe('203.0.113.9')
        ->and($order->taker_fingerprint)->toBe('fp-unique-1');
});

it('flags shared_device when the taker uses a device the counterparty logs in from', function () {
    // The seller (counterparty) has logged in from this device.
    UserDevice::create(['user_id' => $this->seller->id, 'fingerprint' => 'shared-fp', 'ip_address' => '203.0.113.9']);

    $order = app(CreateOrderAction::class)->execute(
        $this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'),
        fingerprint: 'shared-fp',
    );

    expect($order->meta['risk']['reasons'] ?? [])->toContain('shared_device')
        ->and($order->meta['risk']['score'])->toBeGreaterThanOrEqual(50);
});

it('flags shared_device when the counterparty traded from the same device before', function () {
    // Seller previously took an order from this fingerprint.
    $seller2 = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($seller2, $this->usdt, '1000000000');
    $ad2 = P2pAd::factory()->create(['user_id' => $seller2->id, 'asset_id' => $this->usdt->id]);

    app(CreateOrderAction::class)->execute($this->seller, $ad2, Money::ofDecimal('50', 6, 'USDT'), fingerprint: 'reused-fp');

    // Now the buyer trades with the seller from that same fingerprint.
    $order = app(CreateOrderAction::class)->execute(
        $this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'),
        fingerprint: 'reused-fp',
    );

    expect($order->meta['risk']['reasons'] ?? [])->toContain('shared_device');
});

it('does not flag when devices differ', function () {
    UserDevice::create(['user_id' => $this->seller->id, 'fingerprint' => 'seller-device', 'ip_address' => '10.0.0.1']);

    $order = app(CreateOrderAction::class)->execute(
        $this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'),
        fingerprint: 'buyer-device',
    );

    expect($order->meta['risk']['reasons'] ?? [])->not->toContain('shared_device');
});
