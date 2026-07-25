<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Enums\OrderStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Order;
use App\Shop\Models\OrderItem;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;

beforeEach(function () {
    updateSetting('sell_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');
});

it('shows the become-a-seller onboarding to a non-seller', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('sell'))
        ->assertOk()
        ->assertSee('Start selling in 3 steps')
        ->assertSee(route('sell.apply'), false);
});

it('renders the seller application form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('sell.apply'))
        ->assertOk()
        ->assertSee('Become a Seller')
        ->assertSee('How it works')
        ->assertSee('What will you sell?')
        ->assertSee(route('sell.apply.submit'), false);
});

it('renders the active dashboard with store name, workspace and recent orders', function () {
    $user = User::factory()->create();
    $seller = Seller::create([
        'user_id' => $user->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim Studios', 'categories' => [],
    ]);

    $product = Product::create([
        'seller_id' => $seller->id, 'type' => ProductType::Digital, 'name' => 'Kit',
        'slug' => 'kit', 'status' => ProductStatus::Published, 'price_amount' => 10_000000, 'price_asset_id' => $this->asset->id,
    ]);
    SalesPage::create([
        'seller_id' => $seller->id, 'product_id' => $product->id, 'name' => 'Main', 'slug' => 'kit-main',
        'status' => SalesPageStatus::Published, 'sections' => [], 'theme' => [], 'version' => 1, 'published_at' => now(),
    ]);

    $buyer = User::factory()->create();
    $order = Order::create([
        'seller_id' => $seller->id, 'buyer_user_id' => $buyer->id, 'number' => 'ORD-1',
        'idempotency_key' => 'test-order-1',
        'status' => OrderStatus::Paid, 'asset_id' => $this->asset->id,
        'total_amount' => 10_000000, 'seller_net_amount' => 9_000000, 'commission_amount' => 1_000000,
        'seller_unread' => true,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'name_snapshot' => 'Kit',
        'quantity' => 1, 'unit_amount' => 10_000000, 'line_total_amount' => 10_000000,
        'commission_amount' => 1_000000, 'seller_net_amount' => 9_000000,
    ]);

    $this->actingAs($user)
        ->get(route('sell'))
        ->assertOk()
        ->assertSee('Rahim Studios')
        ->assertSee('Recent orders')
        ->assertSee('Needs attention')      // a paid order awaits fulfilment
        ->assertSee('Catalog')
        ->assertSee('Last 30 days')         // performance section
        ->assertSee('Conversion funnel')
        ->assertSee('Top products')
        ->assertSee(route('sell.order', $order->id), false);
});
