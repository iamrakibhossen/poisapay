<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Product;
use App\Shop\Models\ProductFile;
use App\Shop\Models\Seller;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');

    $this->buyer = User::factory()->create();
    creditUser($this->buyer, $this->asset, '100000000');

    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim Studios', 'categories' => [], 'commission_bps' => 1000,
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital,
        'name' => 'LaunchKit', 'slug' => 'launchkit', 'status' => ProductStatus::Published,
        'price_amount' => 10_000000, 'price_asset_id' => $this->asset->id,
    ]);
    ProductFile::create([
        'product_id' => $this->product->id, 'disk' => 'local', 'path' => 'sell/launchkit.zip',
        'original_name' => 'launchkit-v1.0.zip', 'is_current' => true,
    ]);
    $this->order = app(PlaceOrder::class)->execute($this->buyer, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'quantity' => 1, 'idempotency_key' => 'pd-1',
    ]));
});

it('shows the buyer their single purchase in full', function () {
    $this->actingAs($this->buyer)->get(route('purchases.show', ['order' => $this->order->id]))
        ->assertOk()
        ->assertSee($this->order->number)
        ->assertSee('LaunchKit')
        ->assertSee('Rahim Studios')
        ->assertSee('10.00 USDT')
        ->assertSee('launchkit-v1.0.zip');
});

it('redirects away from an order the user does not own', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('purchases.show', ['order' => $this->order->id]))
        ->assertRedirect(route('purchases'));
});

it('redirects for an unknown order id', function () {
    $this->actingAs($this->buyer)->get(route('purchases.show', ['order' => '00000000-0000-0000-0000-000000000000']))
        ->assertRedirect(route('purchases'));
});
