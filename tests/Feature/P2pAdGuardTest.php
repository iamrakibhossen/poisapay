<?php

declare(strict_types=1);

use App\Domain\P2p\CreateOrderAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\P2pOrderStatus;
use App\Models\KycProfile;
use App\Models\P2pAd;
use App\Models\P2pMerchantProfile;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);

    $this->usdt = testAsset('USDT', 6, 'tron');

    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000'); // 1000 USDT
});

function guardAd(array $overrides = []): P2pAd
{
    return P2pAd::factory()->create(array_merge([
        'user_id' => test()->seller->id,
        'asset_id' => test()->usdt->id,
    ], $overrides));
}

// ── Trading hours ─────────────────────────────────────────────

it('rejects an order placed outside the ad trading hours', function () {
    $today = now()->dayOfWeek;
    $ad = guardAd(['trade_hours' => ['windows' => [
        ['days' => [($today + 1) % 7], 'start' => '00:00', 'end' => '24:00'],
    ]]]);

    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(RuntimeException::class, 'outside its trading hours');
});

it('allows an order inside the ad trading hours', function () {
    $today = now()->dayOfWeek;
    $ad = guardAd(['trade_hours' => ['windows' => [
        ['days' => [$today], 'start' => '00:00', 'end' => '24:00'],
    ]]]);

    $order = app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('100', 6, 'USDT'));

    expect($order->status)->toBe(P2pOrderStatus::WaitingPayment);
});

it('treats an empty trade-hours config as always open', function () {
    $ad = guardAd(['trade_hours' => null]);

    $order = app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('100', 6, 'USDT'));

    expect($order->status)->toBe(P2pOrderStatus::WaitingPayment);
});

// ── Per-ad daily limit ────────────────────────────────────────

it('enforces the ad daily volume limit across orders', function () {
    $ad = guardAd(['daily_limit' => Money::ofDecimal('150', 6, 'USDT')->baseString()]);

    $first = app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('100', 6, 'USDT'));
    expect($first->status)->toBe(P2pOrderStatus::WaitingPayment);

    // 100 already used today; another 100 would breach the 150 cap.
    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(RuntimeException::class, 'daily trading limit');
});

it('ignores a null or zero daily limit', function () {
    $ad = guardAd(['daily_limit' => null]);

    $order = app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('500', 6, 'USDT'));

    expect($order->status)->toBe(P2pOrderStatus::WaitingPayment);
});

// ── Merchant completion requirement ───────────────────────────

it('blocks a taker below the ad completion requirement', function () {
    $ad = guardAd(['min_completion_bps' => 9500]);
    P2pMerchantProfile::create([
        'user_id' => $this->buyer->id,
        'trade_count' => 10,
        'completed_count' => 8,
        'completion_rate_bps' => 8000,
    ]);

    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(RuntimeException::class, 'merchant requirement');
});

it('allows a taker who meets the completion requirement', function () {
    $ad = guardAd(['min_completion_bps' => 9500]);
    P2pMerchantProfile::create([
        'user_id' => $this->buyer->id,
        'trade_count' => 10,
        'completed_count' => 10,
        'completion_rate_bps' => 9600,
    ]);

    $order = app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('100', 6, 'USDT'));

    expect($order->status)->toBe(P2pOrderStatus::WaitingPayment);
});

it('does not judge a brand-new taker on completion rate', function () {
    $ad = guardAd(['min_completion_bps' => 9500]); // no profile / no history for buyer

    $order = app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('100', 6, 'USDT'));

    expect($order->status)->toBe(P2pOrderStatus::WaitingPayment);
});

// ── Country restriction ───────────────────────────────────────

it('blocks a taker whose country is not allowed by the ad', function () {
    $ad = guardAd(['countries' => ['US']]);
    KycProfile::create([
        'user_id' => $this->buyer->id,
        'requested_tier' => KycTier::Full,
        'status' => KycStatus::Approved,
        'country' => 'BD',
    ]);

    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(RuntimeException::class, 'not available in your country');
});

it('allows a taker whose country is on the ad allow-list', function () {
    $ad = guardAd(['countries' => ['us', 'bd']]); // case-insensitive
    KycProfile::create([
        'user_id' => $this->buyer->id,
        'requested_tier' => KycTier::Full,
        'status' => KycStatus::Approved,
        'country' => 'BD',
    ]);

    $order = app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('100', 6, 'USDT'));

    expect($order->status)->toBe(P2pOrderStatus::WaitingPayment);
});

it('does not restrict a taker whose country is unknown', function () {
    $ad = guardAd(['countries' => ['US']]); // buyer has no KYC country on file

    $order = app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('100', 6, 'USDT'));

    expect($order->status)->toBe(P2pOrderStatus::WaitingPayment);
});
