<?php

declare(strict_types=1);

use App\Domain\P2p\ConfirmReleaseAction;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
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

it('renders the merchant dashboard with aggregated KPIs', function () {
    $this->actingAs($this->seller)
        ->get(route('p2p.dashboard'))
        ->assertOk()
        ->assertSee('P2P Dashboard')
        ->assertSee('Active ads')
        ->assertSee('Completed trades')
        ->assertViewHas('activeAds', 1)
        ->assertViewHas('kpis');
});

it('reflects a completed trade in the KPIs and outcome chart', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));
    app(MarkBuyerPaidAction::class)->execute($order->refresh(), $this->buyer);
    app(ConfirmReleaseAction::class)->execute($order->refresh(), $this->seller);

    $res = $this->actingAs($this->seller)->get(route('p2p.dashboard'))->assertOk();

    // Outcome doughnut: [completed, cancelled/expired, open, disputed]
    expect($res->viewData('outcomeChart')['datasets'][0]['data'][0])->toBe(1)
        ->and($res->viewData('orders30'))->toBe(1)
        ->and($res->viewData('successRate30'))->toBe(100.0);

    // 14-day volume series carries the 100 USDT trade on the last day.
    $vol = $res->viewData('volumeChart')['datasets'][0]['data'];
    expect(array_sum($vol))->toBe(100.0);
});

it('renders cleanly for a merchant with no ads or orders', function () {
    $newbie = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);

    $this->actingAs($newbie)
        ->get(route('p2p.dashboard'))
        ->assertOk()
        ->assertViewHas('activeAds', 0)
        ->assertViewHas('orders30', 0);
});

it('404s when the P2P flag is off', function () {
    updateSetting('p2p_enabled', false);

    $this->actingAs($this->seller)
        ->get(route('p2p.dashboard'))
        ->assertNotFound();
});
