<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Enums\OrderItemKind;
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
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');

    $this->user = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->user->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim Studios', 'categories' => [], 'commission_bps' => 1000,
    ]);

    $mk = fn (string $name, int $price) => Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => $name,
        'slug' => strtolower($name).'-'.uniqid(), 'status' => ProductStatus::Published,
        'price_amount' => $price, 'price_asset_id' => $this->asset->id,
    ]);
    $this->front = $mk('LaunchKit', 10_000000);
    $this->bumpP = $mk('UI Kit', 5_000000);
    $this->upsellP = $mk('Team License', 20_000000);

    $this->page = SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $this->front->id,
        'name' => 'LaunchKit funnel', 'slug' => 'launchkit', 'status' => SalesPageStatus::Published,
        'sections' => [], 'theme' => [], 'version' => 1, 'published_at' => now(),
        'bump_product_id' => $this->bumpP->id, 'bump_price_amount' => 3_000000, 'bump_headline' => 'Add the UI Kit',
        'upsell_product_id' => $this->upsellP->id, 'upsell_price_amount' => 12_000000, 'upsell_headline' => 'Go team',
    ]);
});

function mainOrder(Seller $seller, SalesPage $page, int $asset, int $net, bool $withBump = false): Order
{
    $order = Order::create([
        'seller_id' => $seller->id, 'buyer_user_id' => User::factory()->create()->id,
        'number' => 'ORD-'.uniqid(), 'idempotency_key' => 'k-'.uniqid(),
        'sales_page_id' => $page->id, 'status' => OrderStatus::Paid, 'asset_id' => $asset,
        'total_amount' => $net, 'seller_net_amount' => $net, 'commission_amount' => 0,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $page->product_id, 'name_snapshot' => 'Main',
        'quantity' => 1, 'unit_amount' => $net, 'line_total_amount' => $net, 'seller_net_amount' => $net,
    ]);
    if ($withBump) {
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $page->bump_product_id, 'kind' => OrderItemKind::OrderBump,
            'name_snapshot' => 'Bump', 'quantity' => 1, 'unit_amount' => 3_000000,
            'line_total_amount' => 3_000000, 'seller_net_amount' => 3_000000,
        ]);
    }

    return $order;
}

it('lists a published page as a funnel with its configured offers', function () {
    $this->actingAs($this->user)
        ->get(route('shop.funnels'))
        ->assertOk()
        ->assertSee('LaunchKit funnel')
        ->assertSee('Add the UI Kit')      // bump headline
        ->assertSee('Go team')             // upsell headline
        ->assertSee('Order bump')
        ->assertSee('1-click upsell')
        ->assertSee(route('shop.sales-page.edit', ['slug' => 'launchkit']).'?tab=settings', false);
});

it('computes live 30-day take rates and extra revenue from real orders', function () {
    // 4 front sales; 2 take the bump, 1 takes the upsell.
    mainOrder($this->seller, $this->page, $this->asset->id, 10_000000, withBump: true);
    mainOrder($this->seller, $this->page, $this->asset->id, 10_000000, withBump: true);
    mainOrder($this->seller, $this->page, $this->asset->id, 10_000000);
    $parent = mainOrder($this->seller, $this->page, $this->asset->id, 10_000000);

    Order::create([
        'seller_id' => $this->seller->id, 'buyer_user_id' => $parent->buyer_user_id,
        'number' => 'ORD-up', 'idempotency_key' => 'up-1', 'sales_page_id' => $this->page->id,
        'parent_order_id' => $parent->id, 'status' => OrderStatus::Paid, 'asset_id' => $this->asset->id,
        'total_amount' => 12_000000, 'seller_net_amount' => 12_000000, 'commission_amount' => 0,
    ]);

    $res = $this->actingAs($this->user)->get(route('shop.funnels'))->assertOk();

    // bump: 2/4 = 50% take; upsell: 1/4 = 25% take.
    $res->assertSee('50% take')->assertSee('25% take');
    // extra revenue = 2×3 (bump) + 12 (upsell) = 18 USDT.
    $res->assertSee('18.00');
});

it('prompts to add offers when a published page has none', function () {
    $this->page->update(['bump_product_id' => null, 'upsell_product_id' => null]);

    $this->actingAs($this->user)
        ->get(route('shop.funnels'))
        ->assertOk()
        ->assertSee('Add an order bump or upsell');
});

it('shows an empty state when the seller has no published pages', function () {
    $this->page->update(['status' => SalesPageStatus::Draft]);

    $this->actingAs($this->user)
        ->get(route('shop.funnels'))
        ->assertOk()
        ->assertSee('No published sales pages yet');
});
