<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Sell\Actions\Order\PlaceOrder;
use App\Sell\Actions\Order\SetOrderStatus;
use App\Sell\DTOs\CheckoutData;
use App\Sell\Enums\OrderStatus;
use App\Sell\Enums\ProductStatus;
use App\Sell\Enums\ProductType;
use App\Sell\Enums\SellerStatus;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Product;
use App\Sell\Models\Seller;

beforeEach(function () {
    updateSetting('sell_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');

    $this->buyer = User::factory()->create();
    creditUser($this->buyer, $this->asset, '100000000');

    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim Studios', 'categories' => [], 'commission_bps' => 1000,
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Physical,
        'name' => 'Dev Tee', 'slug' => 'dev-tee', 'status' => ProductStatus::Published,
        'price_amount' => 25_000000, 'price_asset_id' => $this->asset->id, 'requires_shipping' => true,
    ]);

    $this->order = app(PlaceOrder::class)->execute($this->buyer, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'quantity' => 1, 'idempotency_key' => 'ff-1',
    ]));
});

it('advances paid → processing → shipped and records events + audit', function () {
    $action = app(SetOrderStatus::class);

    $action->execute($this->order, OrderStatus::Processing);
    $order = $action->execute($this->order->fresh(), OrderStatus::Shipped, ['carrier' => 'Pathao', 'tracking' => 'BD-99']);

    expect($order->status)->toBe(OrderStatus::Shipped)
        ->and($order->shipping_address['carrier'])->toBe('Pathao')
        ->and($order->shipping_address['tracking'])->toBe('BD-99')
        ->and($order->events()->where('type', 'shipped')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'sell.order.shipped')->exists())->toBeTrue();
});

it('rejects an illegal transition (paid → delivered)', function () {
    expect(fn () => app(SetOrderStatus::class)->execute($this->order, OrderStatus::Delivered))
        ->toThrow(SellException::class);
});

it('refuses money-reversal states through fulfilment', function () {
    expect(fn () => app(SetOrderStatus::class)->execute($this->order, OrderStatus::Refunded))
        ->toThrow(SellException::class);
});

it('lets the owning seller advance the order over HTTP', function () {
    $this->actingAs($this->sellerUser)
        ->post(route('sell.order.status', ['id' => $this->order->id]), ['status' => 'processing'])
        ->assertRedirect(route('sell.order', ['id' => $this->order->id]));

    expect($this->order->fresh()->status)->toBe(OrderStatus::Processing);
});

it('forbids a different seller from touching the order', function () {
    $other = User::factory()->create();
    Seller::create(['user_id' => $other->id, 'status' => SellerStatus::Approved, 'categories' => []]);

    $this->actingAs($other)
        ->post(route('sell.order.status', ['id' => $this->order->id]), ['status' => 'processing'])
        ->assertRedirect(route('sell.orders')); // resolved away — not their order

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('shows the order list and detail to the seller', function () {
    $this->actingAs($this->sellerUser)->get(route('sell.orders'))
        ->assertOk()->assertSee($this->order->number)->assertSee('Dev Tee');

    $this->actingAs($this->sellerUser)->get(route('sell.order', ['id' => $this->order->id]))
        ->assertOk()->assertSee('Dev Tee')->assertSee('Fulfilment');
});
