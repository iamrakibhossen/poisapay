<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');

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
});

$buy = function (User $buyer, string $key) {
    return app(PlaceOrder::class)->execute($buyer, CheckoutData::fromArray([
        'product_id' => test()->product->id, 'quantity' => 1, 'idempotency_key' => $key,
    ]));
};

it('aggregates real customers ranked by spend', function () use ($buy) {
    $whale = User::factory()->create(['name' => 'Big Spender']);
    creditUser($whale, $this->asset, '100000000');
    $buy($whale, 'w1');
    $buy($whale, 'w2'); // 2 orders = 20 USDT

    $small = User::factory()->create(['name' => 'Small Buyer']);
    creditUser($small, $this->asset, '100000000');
    $buy($small, 's1'); // 1 order = 10 USDT

    $res = $this->actingAs($this->sellerUser)->get(route('shop.customers'))->assertOk();
    $customers = $res->viewData('customers');

    expect($customers)->toHaveCount(2)
        ->and($customers[0]['name'])->toBe('Big Spender')   // ranked first by spend
        ->and($customers[0]['orders'])->toBe(2)
        ->and($customers[0]['spent'])->toBe('20.00 USDT')
        ->and($customers[1]['name'])->toBe('Small Buyer');

    $res->assertSee('Big Spender')->assertSee('20.00 USDT');
});

it('shows an empty state with no customers', function () {
    $this->actingAs($this->sellerUser)->get(route('shop.customers'))
        ->assertOk()->assertSee('No customers yet');
});

it('excludes another seller\'s customers', function () use ($buy) {
    $buyer = User::factory()->create(['name' => 'Mine']);
    creditUser($buyer, $this->asset, '100000000');
    $buy($buyer, 'm1');

    $otherUser = User::factory()->create();
    $otherSeller = Seller::create(['user_id' => $otherUser->id, 'status' => SellerStatus::Approved, 'categories' => []]);

    $this->actingAs($otherUser)->get(route('shop.customers'))
        ->assertOk()->assertDontSee('Mine');
});
