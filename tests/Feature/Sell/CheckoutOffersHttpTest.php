<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\User;
use App\Sell\Enums\OrderItemKind;
use App\Sell\Enums\ProductStatus;
use App\Sell\Enums\ProductType;
use App\Sell\Enums\SalesPageStatus;
use App\Sell\Enums\SellerStatus;
use App\Sell\Models\Order;
use App\Sell\Models\Product;
use App\Sell\Models\SalesPage;
use App\Sell\Models\Seller;

beforeEach(function () {
    updateSetting('sell_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);

    $this->buyer = User::factory()->create();
    creditUser($this->buyer, $this->asset, '100000000'); // 100 USDT

    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim', 'categories' => [], 'commission_bps' => 1000,
    ]);
    $mk = fn (string $n, int $p) => Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => $n,
        'slug' => strtolower($n), 'status' => ProductStatus::Published, 'price_amount' => $p, 'price_asset_id' => $this->asset->id,
    ]);
    $this->product = $mk('Main', 10_000000);
    $this->bumpP = $mk('Bump', 5_000000);
    $this->upsellP = $mk('Upsell', 20_000000);

    $this->page = SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'name' => 'Main',
        'slug' => 'main', 'status' => SalesPageStatus::Published, 'sections' => [], 'theme' => [], 'version' => 1, 'published_at' => now(),
        'bump_product_id' => $this->bumpP->id, 'bump_price_amount' => 3_000000, 'bump_headline' => 'Add the toolkit',
        'upsell_product_id' => $this->upsellP->id, 'upsell_price_amount' => 12_000000, 'upsell_headline' => 'Add the Pro pack',
    ]);
});

it('shows the order bump on the pay page', function () {
    $this->actingAs($this->buyer)->get('/p/main/pay')
        ->assertOk()->assertSee('Add the toolkit')->assertSee('3.00 USDT');
});

it('charges the combined total when the bump is accepted at checkout', function () {
    $this->actingAs($this->buyer)
        ->post('/p/main/pay', ['idempotency_key' => 'k1', 'bump' => '1'])
        ->assertRedirect(route('funnel.thankyou', ['slug' => 'main']));

    $order = Order::where('buyer_user_id', $this->buyer->id)->first();
    expect($order->items()->count())->toBe(2)
        ->and((int) $order->total_amount)->toBe(13_000000)
        ->and($this->ledger->availableBalance($this->buyer, $this->asset->id)->baseString())->toBe('87000000');
});

it('offers the 1-click upsell on the thank-you page and accepts it', function () {
    // Buy the main product first.
    $this->actingAs($this->buyer)->post('/p/main/pay', ['idempotency_key' => 'k2']);
    $parent = Order::where('buyer_user_id', $this->buyer->id)->latest()->first();

    // Thank-you shows the upsell.
    $this->actingAs($this->buyer)->get('/p/main/thank-you')
        ->assertOk()->assertSee('Add the Pro pack')->assertSee('12.00 USDT');

    // Accept it — one click.
    $this->actingAs($this->buyer)->post('/p/main/upsell')
        ->assertRedirect(route('funnel.thankyou', ['slug' => 'main']));

    $upsell = Order::where('parent_order_id', $parent->id)->first();
    expect($upsell)->not->toBeNull()
        ->and((int) $upsell->total_amount)->toBe(12_000000)
        ->and($upsell->items()->first()->kind)->toBe(OrderItemKind::Upsell)
        // 100 - 10 - 12 = 78
        ->and($this->ledger->availableBalance($this->buyer, $this->asset->id)->baseString())->toBe('78000000');
});

it('does not offer the upsell twice (idempotent)', function () {
    $this->actingAs($this->buyer)->post('/p/main/pay', ['idempotency_key' => 'k3']);
    $this->actingAs($this->buyer)->post('/p/main/upsell');
    $this->actingAs($this->buyer)->post('/p/main/upsell'); // second attempt = no-op

    expect(Order::where('parent_order_id', '!=', null)->whereNotNull('parent_order_id')->count())->toBe(1);
});

it('lets the seller configure offers from the builder', function () {
    $this->actingAs($this->sellerUser)
        ->post(route('sell.sales-page.update', ['slug' => 'main']), [
            'name' => 'Main', 'builder' => json_encode(['theme' => [], 'sections' => []]),
            'bump_product_id' => $this->bumpP->id, 'bump_price' => '4', 'bump_headline' => 'Grab this',
            'upsell_product_id' => $this->upsellP->id, 'upsell_price' => '15',
        ])
        ->assertRedirect(route('sell.sales-page.edit', ['slug' => 'main']));

    $page = $this->page->fresh();
    expect($page->bump_product_id)->toBe($this->bumpP->id)
        ->and((int) $page->bump_price_amount)->toBe(4_000000)
        ->and((int) $page->upsell_price_amount)->toBe(15_000000);
});
