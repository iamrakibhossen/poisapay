<?php

declare(strict_types=1);

use App\Domain\P2p\ConfirmReleaseAction;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Domain\P2p\P2pReputationService;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\P2pAd;
use App\Models\P2pMerchantProfile;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);
    updateSetting('p2p_high_value_usdt', 0); // keep risk quiet for these tests

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000');
    $this->ad = P2pAd::factory()->create(['user_id' => $this->seller->id, 'asset_id' => $this->usdt->id]);
});

it('maps completed trade count to a level', function () {
    $rep = app(P2pReputationService::class);
    expect($rep->level(0))->toBe(0)
        ->and($rep->level(10))->toBe(1)
        ->and($rep->level(50))->toBe(2)
        ->and($rep->level(200))->toBe(3)
        ->and($rep->level(1000))->toBe(4);
});

it('awards badges from stats + KYC', function () {
    $profile = P2pMerchantProfile::create([
        'user_id' => $this->seller->id,
        'trade_count' => 30, 'completed_count' => 30, 'completion_rate_bps' => 10000,
        'avg_release_seconds' => 200, 'avg_pay_seconds' => 500, 'total_volume' => '200000000000',
    ]);

    app(P2pReputationService::class)->recompute($profile);

    expect($profile->fresh()->badges)
        ->toContain('verified')      // Full KYC
        ->toContain('reliable')      // 100% over 20+ trades
        ->toContain('fast_release')  // avg release <= 5m
        ->toContain('fast_pay')      // avg pay <= 15m
        ->toContain('high_volume');  // >= 100k USDT
});

it('recomputes reputation when a trade completes', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));
    app(MarkBuyerPaidAction::class)->execute($order->refresh(), $this->buyer);
    app(ConfirmReleaseAction::class)->execute($order->refresh(), $this->seller);

    $profile = P2pMerchantProfile::where('user_id', $this->seller->id)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->completed_count)->toBe(1)
        ->and($profile->badges)->toContain('verified'); // recompute ran during release
});

it('hides vacation-mode advertisers from the marketplace', function () {
    P2pMerchantProfile::create(['user_id' => $this->seller->id, 'vacation_mode' => true]);
    $this->actingAs($this->buyer);

    $this->get(route('p2p'))->assertOk()->assertDontSee($this->seller->name);
});

it('renders a trader profile and lets a trader toggle vacation', function () {
    $this->actingAs($this->buyer)->get(route('p2p.merchant', $this->seller))->assertOk()->assertSee($this->seller->name);

    $this->actingAs($this->seller)->post(route('p2p.merchant.vacation'))->assertRedirect();
    expect(P2pMerchantProfile::where('user_id', $this->seller->id)->first()->vacation_mode)->toBeTrue();
});
