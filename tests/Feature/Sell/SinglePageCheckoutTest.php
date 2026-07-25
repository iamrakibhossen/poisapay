<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\User;
use App\Shop\Enums\OrderStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Order;
use App\Shop\Models\Product;
use App\Shop\Models\ProductVariant;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;

beforeEach(function () {
    updateSetting('sell_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);

    $this->buyer = User::factory()->create();
    creditUser($this->buyer, $this->asset, '100000000');

    $this->seller = Seller::create([
        'user_id' => User::factory()->create()->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim', 'categories' => [], 'commission_bps' => 1000,
    ]);
    // Physical variant product with a shipping fee.
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Physical, 'name' => 'Tee',
        'slug' => 'tee', 'status' => ProductStatus::Published, 'requires_shipping' => true, 'has_variants' => true,
        'price_amount' => 20_000000, 'price_asset_id' => $this->asset->id,
        'attributes' => ['shipping_fee' => '2'],
    ]);
    ProductVariant::create(['product_id' => $this->product->id, 'options' => ['Size' => 'M'], 'price_amount' => 20_000000, 'is_active' => true, 'position' => 0]);
    ProductVariant::create(['product_id' => $this->product->id, 'options' => ['Size' => 'L'], 'price_amount' => 25_000000, 'is_active' => true, 'position' => 1]);

    $this->page = SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'name' => 'Main', 'slug' => 'tee-main',
        'status' => SalesPageStatus::Published, 'sections' => [], 'theme' => [], 'version' => 1, 'published_at' => now(),
    ]);
});

it('Buy goes straight to the single-page checkout (no shipping step)', function () {
    $this->actingAs($this->buyer)->post('/p/tee-main/buy')
        ->assertRedirect(route('funnel.pay', ['slug' => 'tee-main']));
});

it('the pay page carries the variation + shipping fields inline', function () {
    $this->actingAs($this->buyer)->get('/p/tee-main/checkout')
        ->assertOk()
        ->assertSee('Delivery address')
        ->assertSee('name="options[Size]"', false)
        ->assertSee('name="line1"', false);
});

it('places a physical variant order with shipping captured at pay', function () {
    $this->actingAs($this->buyer)->post('/p/tee-main/checkout', [
        'idempotency_key' => 'sp-1',
        'options' => ['Size' => 'L'],
        'name' => 'Aisha', 'phone' => '017', 'line1' => 'House 1', 'city' => 'Dhaka', 'country' => 'BD',
    ])->assertRedirect(route('funnel.thankyou', ['slug' => 'tee-main']));

    $order = Order::where('buyer_user_id', $this->buyer->id)->first();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->shipping_address['line1'])->toBe('House 1')
        ->and($order->items()->first()->name_snapshot)->toContain('L')
        // 25 (L variant) + 2 shipping = 27 → balance 100 - 27 = 73
        ->and((int) $order->total_amount)->toBe(27_000000)
        ->and($this->ledger->availableBalance($this->buyer, $this->asset->id)->baseString())->toBe('73000000');
});

it('rejects an invalid variation combo', function () {
    $this->actingAs($this->buyer)->post('/p/tee-main/checkout', [
        'idempotency_key' => 'sp-2',
        'options' => ['Size' => 'XXL'], // not a real variant
        'name' => 'A', 'phone' => '1', 'line1' => 'x', 'city' => 'D', 'country' => 'BD',
    ])->assertSessionHasErrors('options');

    expect(Order::where('buyer_user_id', $this->buyer->id)->count())->toBe(0);
});

it('requires the shipping fields for physical goods', function () {
    $this->actingAs($this->buyer)->post('/p/tee-main/checkout', [
        'idempotency_key' => 'sp-3', 'options' => ['Size' => 'M'],
    ])->assertSessionHasErrors(['line1', 'city', 'country']);
});
