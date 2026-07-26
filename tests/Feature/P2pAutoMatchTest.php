<?php

declare(strict_types=1);

use App\Domain\P2p\NoMatchException;
use App\Domain\P2p\P2pMatchingService;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\P2pAdType;
use App\Models\Asset;
use App\Models\P2pAd;
use App\Models\P2pOrder;
use App\Models\P2pPaymentMethod;
use App\Models\User;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_auto_match', true);
    updateSetting('p2p_taker_fee_bps', 100);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->method = P2pPaymentMethod::firstOrCreate(
        ['key' => 'automatch-bank'],
        ['name' => 'Bank Transfer', 'type' => 'bank', 'is_active' => true, 'sort' => 1],
    );
});

function sellAd(int $assetId, string $price, string $methodId, ?string $available = null): P2pAd
{
    $seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($seller, Asset::find($assetId), '1000000000'); // 1000 USDT for escrow

    $ad = P2pAd::factory()->create([
        'user_id' => $seller->id, 'asset_id' => $assetId, 'side' => P2pAdType::Sell,
        'fixed_price' => $price, 'available_amount' => $available ?? '1000000000',
    ]);
    $ad->paymentMethods()->attach($methodId);

    return $ad;
}

it('matches the cheapest eligible offer (price-time priority)', function () {
    $pricey = sellAd($this->usdt->id, '122.0000', $this->method->id);
    $cheap = sellAd($this->usdt->id, '118.0000', $this->method->id);

    $order = app(P2pMatchingService::class)->match($this->buyer, P2pAdType::Sell, '100', $this->method->id);

    expect($order->ad_id)->toBe($cheap->id)          // cheapest wins
        ->and($order->ad_id)->not->toBe($pricey->id)
        ->and($order->buyer_id)->toBe($this->buyer->id)
        ->and($order->payment_method_id)->toBe($this->method->id);
});

it('falls through to the next offer when the best one cannot fill the amount', function () {
    // Cheapest ad only has 50 USDT available; next one can fill 100.
    $tooSmall = sellAd($this->usdt->id, '118.0000', $this->method->id, '50000000'); // 50 USDT
    $fills = sellAd($this->usdt->id, '120.0000', $this->method->id);

    $order = app(P2pMatchingService::class)->match($this->buyer, P2pAdType::Sell, '100', $this->method->id);

    expect($order->ad_id)->toBe($fills->id);
});

it('throws when no offer accepts the chosen payment method', function () {
    sellAd($this->usdt->id, '118.0000', $this->method->id);
    $other = P2pPaymentMethod::firstOrCreate(['key' => 'automatch-cash'], ['name' => 'Cash', 'type' => 'cash', 'is_active' => true, 'sort' => 2]);

    expect(fn () => app(P2pMatchingService::class)->match($this->buyer, P2pAdType::Sell, '100', $other->id))
        ->toThrow(NoMatchException::class);
});

it('never matches the takers own ad', function () {
    $own = P2pAd::factory()->create([
        'user_id' => $this->buyer->id, 'asset_id' => $this->usdt->id, 'side' => P2pAdType::Sell, 'fixed_price' => '100.0000',
    ]);
    $own->paymentMethods()->attach($this->method->id);

    expect(fn () => app(P2pMatchingService::class)->match($this->buyer, P2pAdType::Sell, '100', $this->method->id))
        ->toThrow(NoMatchException::class);
});

it('opens an order over HTTP and redirects to it', function () {
    $ad = sellAd($this->usdt->id, '118.0000', $this->method->id);

    $this->actingAs($this->buyer)
        ->post(route('p2p.match'), ['side' => 'buy', 'amount' => '100', 'payment_method_id' => $this->method->id])
        ->assertRedirectContains('/p2p/orders/');

    expect(P2pOrder::where('buyer_id', $this->buyer->id)->where('ad_id', $ad->id)->exists())->toBeTrue();
});

it('is blocked when the auto-match flag is off', function () {
    updateSetting('p2p_auto_match', false);
    sellAd($this->usdt->id, '118.0000', $this->method->id);

    $this->actingAs($this->buyer)
        ->post(route('p2p.match'), ['side' => 'buy', 'amount' => '100', 'payment_method_id' => $this->method->id])
        ->assertSessionHas('error');

    expect(P2pOrder::where('buyer_id', $this->buyer->id)->exists())->toBeFalse();
});

it('requires a payment method to place a manual order', function () {
    $ad = sellAd($this->usdt->id, '118.0000', $this->method->id);

    $this->actingAs($this->buyer)
        ->post(route('p2p.orders.store'), ['ad_id' => $ad->id, 'amount' => '100'])
        ->assertSessionHasErrors('payment_method_id');
});
