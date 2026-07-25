<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\Actions\Order\RefundOrder;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\OrderStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\License;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    updateSetting('shop_commission_bps', 1000); // 10%
    $this->ledger = app(LedgerService::class);
    $this->asset = testAsset('USDT', 6, 'tron');

    $sellerUser = User::factory()->create();
    $this->sellerUser = $sellerUser;
    $this->seller = Seller::create([
        'user_id' => $sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Acme', 'categories' => [], 'settlement_asset_id' => $this->asset->id,
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => 'Toolkit',
        'slug' => 'toolkit', 'status' => ProductStatus::Published, 'price_amount' => 50_000000, 'price_asset_id' => $this->asset->id,
    ]);

    $this->buyer = User::factory()->create();
    creditUser($this->buyer, $this->asset, '100000000'); // 100 USDT

    $this->buy = fn () => app(PlaceOrder::class)->execute($this->buyer, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'quantity' => 1, 'idempotency_key' => 'refund-'.uniqid(),
    ]));

    $this->avail = fn (User $u) => $this->ledger->availableBalance($u, $this->asset->id)->baseString();
    $this->locked = fn (User $u) => $this->ledger->lockedBalance($u, $this->asset->id)->baseString();
});

it('refunds a paid order — buyer repaid, seller net + commission reversed', function () {
    $order = ($this->buy)();

    // net 45, commission 5, total 50; seller net is spendable (hold flag off).
    expect(($this->avail)($this->buyer))->toBe('50000000')
        ->and(($this->avail)($this->sellerUser))->toBe('45000000');

    app(RefundOrder::class)->full($order, $this->sellerUser, 'buyer request');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Refunded)
        ->and($order->refunded_at)->not->toBeNull()
        ->and((int) $order->refunded_amount)->toBe(50_000000)
        ->and(($this->avail)($this->buyer))->toBe('100000000')   // fully repaid
        ->and(($this->avail)($this->sellerUser))->toBe('0');     // net clawed back
});

it('refunds a held order out of the seller locked balance', function () {
    updateSetting('shop_earnings_hold', true);
    $order = ($this->buy)();

    expect(($this->locked)($this->sellerUser))->toBe('45000000')
        ->and(($this->avail)($this->sellerUser))->toBe('0');

    app(RefundOrder::class)->full($order, $this->sellerUser);

    expect(($this->locked)($this->sellerUser))->toBe('0')
        ->and(($this->avail)($this->buyer))->toBe('100000000')
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

it('revokes delivered licences on refund', function () {
    $order = ($this->buy)();
    $item = $order->items()->first();
    $lic = License::create([
        'product_id' => $this->product->id, 'order_item_id' => $item->id,
        'key_ciphertext' => 'x', 'status' => 'active', 'delivered_at' => now(),
    ]);

    app(RefundOrder::class)->full($order, $this->sellerUser);

    expect($lic->fresh()->status)->toBe('revoked');
});

it('is idempotent — refunding the same order twice does not double-refund', function () {
    $order = ($this->buy)();
    app(RefundOrder::class)->full($order, $this->sellerUser);
    $repaid = ($this->avail)($this->buyer);

    app(RefundOrder::class)->full($order->fresh(), $this->sellerUser); // same key → no-op

    expect(($this->avail)($this->buyer))->toBe($repaid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

it('never releases earnings for a refunded held order', function () {
    updateSetting('shop_earnings_hold', true);
    $order = ($this->buy)();
    app(RefundOrder::class)->full($order, $this->sellerUser);

    $this->travel(15)->days();
    $this->artisan('poisapay:shop-release-earnings')->assertSuccessful();

    // Money already returned to the buyer; nothing releases to the seller.
    expect(($this->locked)($this->sellerUser))->toBe('0')
        ->and(($this->avail)($this->sellerUser))->toBe('0')
        ->and($order->fresh()->earnings_released_at)->toBeNull();
});

it('lets the seller refund from the order page', function () {
    $order = ($this->buy)();

    $this->actingAs($this->sellerUser)
        ->post(route('shop.order.refund', ['id' => $order->id]), ['reason' => 'changed mind'])
        ->assertRedirect(route('shop.order', ['id' => $order->id]));

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
});
