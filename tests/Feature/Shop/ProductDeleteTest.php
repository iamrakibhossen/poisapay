<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Actions\Product\CreateProduct;
use App\Shop\DTOs\ProductData;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Order;
use App\Shop\Models\OrderItem;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->user = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->user->id, 'status' => SellerStatus::Approved, 'categories' => [],
    ]);
});

function makeProduct(): Product
{
    return app(CreateProduct::class)->execute(test()->seller, ProductData::fromArray([
        'type' => 'digital',
        'name' => 'LaunchKit',
        'summary' => 'Ship fast.',
        'price_amount' => 4900,
        'price_asset_id' => test()->asset->id,
    ]));
}

function orderFor(Product $product): OrderItem
{
    $order = Order::create([
        'number' => 'ORD-'.substr($product->id, 0, 8),
        'idempotency_key' => 'idem-'.$product->id,
        'seller_id' => test()->seller->id,
        'buyer_user_id' => User::factory()->create()->id,
        'asset_id' => test()->asset->id,
        'total_amount' => 4900,
    ]);

    return OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'kind' => 'main',
        'name_snapshot' => $product->name,
        'unit_amount' => 4900,
        'quantity' => 1,
        'line_total_amount' => 4900,
    ]);
}

it('deletes a product that has no orders (soft delete)', function () {
    $product = makeProduct();

    $this->actingAs($this->user)
        ->delete(route('shop.products.delete', $product->id))
        ->assertRedirect(route('shop.products'))
        ->assertSessionHas('success');

    expect(Product::find($product->id))->toBeNull()                    // hidden by soft-delete
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('blocks deleting a product that has an order', function () {
    $product = makeProduct();
    orderFor($product);

    $this->actingAs($this->user)
        ->delete(route('shop.products.delete', $product->id))
        ->assertSessionHas('error');

    expect(Product::find($product->id))->not->toBeNull();
});

it('exposes canDelete=false to the edit page once a product has orders', function () {
    $product = makeProduct();
    orderFor($product);

    $this->actingAs($this->user)
        ->get(route('shop.products.edit', $product->id))
        ->assertOk()
        ->assertViewHas('canDelete', false);
});

it('exposes canDelete=true on the edit page for an order-free product', function () {
    $product = makeProduct();

    $this->actingAs($this->user)
        ->get(route('shop.products.edit', $product->id))
        ->assertOk()
        ->assertViewHas('canDelete', true);
});

it('does not let another seller delete a product they do not own', function () {
    $product = makeProduct();

    $otherUser = User::factory()->create();
    Seller::create(['user_id' => $otherUser->id, 'status' => SellerStatus::Approved, 'categories' => []]);

    $this->actingAs($otherUser)
        ->delete(route('shop.products.delete', $product->id))
        ->assertNotFound();

    expect(Product::find($product->id))->not->toBeNull();
});
