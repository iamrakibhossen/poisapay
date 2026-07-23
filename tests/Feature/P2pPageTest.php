<?php

declare(strict_types=1);

use App\Domain\P2p\CreateOrderAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\P2pAd;
use App\Models\P2pOrder;
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
    $this->order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));
});

it('renders all consumer P2P pages for a signed-in user', function () {
    $this->actingAs($this->buyer);

    $this->get(route('p2p'))->assertOk()->assertSee('Marketplace');
    $this->get(route('p2p.ads'))->assertOk();
    $this->get(route('p2p.ads.create'))->assertOk()->assertSee('Post a P2P ad');
    $this->get(route('p2p.orders'))->assertOk();
    $this->get(route('p2p.order', $this->order))->assertOk()->assertSee($this->order->ref);
});

it('404s the whole surface when the flag is off', function () {
    updateSetting('p2p_enabled', false);
    $this->actingAs($this->buyer);

    $this->get(route('p2p'))->assertNotFound();
    $this->get(route('p2p.order', $this->order))->assertNotFound();
});

it('opens an order from the marketplace via a form POST', function () {
    $this->actingAs($this->buyer);

    $buyer2 = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->actingAs($buyer2);

    $this->post(route('p2p.orders.store'), ['ad_id' => $this->ad->id, 'amount' => '50'])
        ->assertRedirect();

    expect(P2pOrder::where('buyer_id', $buyer2->id)->count())->toBe(1);
});

it('blocks a non-party from viewing an order', function () {
    $stranger = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->actingAs($stranger);

    $this->get(route('p2p.order', $this->order))->assertForbidden();
});
