<?php

declare(strict_types=1);

use App\Domain\P2p\CreateOrderAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\P2pAd;
use App\Models\P2pBlock;
use App\Models\P2pFavorite;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->seller = User::factory()->create(['name' => 'SellerCo', 'kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000');
    $this->ad = P2pAd::factory()->create(['user_id' => $this->seller->id, 'asset_id' => $this->usdt->id]);
});

it('toggles a favourite merchant on and off', function () {
    $this->actingAs($this->buyer)
        ->post(route('p2p.merchant.favourite', $this->seller))
        ->assertRedirect();

    expect(P2pFavorite::where('user_id', $this->buyer->id)->where('merchant_id', $this->seller->id)->exists())->toBeTrue();

    $this->actingAs($this->buyer)->post(route('p2p.merchant.favourite', $this->seller));

    expect(P2pFavorite::where('user_id', $this->buyer->id)->where('merchant_id', $this->seller->id)->exists())->toBeFalse();
});

it('filters the marketplace to favourites only', function () {
    P2pFavorite::create(['user_id' => $this->buyer->id, 'merchant_id' => $this->seller->id]);
    $other = User::factory()->create(['name' => 'OtherCo', 'kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    P2pAd::factory()->create(['user_id' => $other->id, 'asset_id' => $this->usdt->id]);

    $this->actingAs($this->buyer)
        ->get(route('p2p', ['side' => 'buy', 'fav' => 1]))
        ->assertOk()
        ->assertSee('SellerCo')
        ->assertDontSee('OtherCo');
});

it('blocks a merchant and hides their ads from the marketplace', function () {
    $this->actingAs($this->buyer)
        ->post(route('p2p.merchant.block', $this->seller))
        ->assertRedirect();

    expect(P2pBlock::where('user_id', $this->buyer->id)->where('blocked_id', $this->seller->id)->exists())->toBeTrue();

    $this->actingAs($this->buyer)
        ->get(route('p2p', ['side' => 'buy']))
        ->assertOk()
        ->assertDontSee('SellerCo');
});

it('prevents opening an order with a blocked merchant (either direction)', function () {
    // Seller blocks the buyer.
    P2pBlock::create(['user_id' => $this->seller->id, 'blocked_id' => $this->buyer->id]);

    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(RuntimeException::class, 'cannot trade with this merchant');
});

it('cannot favourite or block yourself', function () {
    $this->actingAs($this->seller)
        ->post(route('p2p.merchant.favourite', $this->seller))
        ->assertForbidden();

    $this->actingAs($this->seller)
        ->post(route('p2p.merchant.block', $this->seller))
        ->assertForbidden();
});
