<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\User;
use App\Sell\Enums\OrderStatus;
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

    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim Studios', 'categories' => [], 'commission_bps' => 1000,
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital,
        'name' => 'LaunchKit', 'slug' => 'launchkit', 'status' => ProductStatus::Published,
        'price_amount' => 10_000000, 'price_asset_id' => $this->asset->id, // 10 USDT
    ]);
    $this->page = SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $this->product->id,
        'name' => 'LaunchKit — Main', 'slug' => 'launchkit-main', 'status' => SalesPageStatus::Published,
        'sections' => [
            ['type' => 'hero', 'enabled' => true, 'content' => ['headline' => 'Ship faster', 'tagline' => 'A Laravel starter']],
            ['type' => 'benefits', 'enabled' => true, 'content' => ['Save time', 'Clean code']],
        ],
        'theme' => ['accent' => '#059669', 'btn' => 'pill', 'font' => 'Inter'],
        'version' => 1, 'published_at' => now(),
    ]);
});

it('renders a published page with the seller content', function () {
    $this->get('/p/launchkit-main')
        ->assertOk()
        ->assertSee('Ship faster')
        ->assertSee('Rahim Studios')
        ->assertSee('10.00 USDT');
});

it('404s for an unpublished or unknown page', function () {
    $this->page->update(['status' => SalesPageStatus::Draft]);

    $this->get('/p/launchkit-main')->assertNotFound();
    $this->get('/p/does-not-exist')->assertNotFound();
});

it('sends a guest to the express-account step before paying', function () {
    $this->post('/p/launchkit-main/checkout')->assertRedirect(route('funnel.account', ['slug' => 'launchkit-main']));
});

it('shows the pay page with the buyer wallet balance', function () {
    $buyer = User::factory()->create();
    creditUser($buyer, $this->asset, '25000000'); // 25 USDT

    $this->actingAs($buyer)->get('/p/launchkit-main/pay')
        ->assertOk()
        ->assertSee('10.00 USDT')       // total
        ->assertSee('25.00 USDT');      // balance
});

it('places a real order and moves money through the ledger', function () {
    $buyer = User::factory()->create();
    creditUser($buyer, $this->asset, '100000000'); // 100 USDT

    $this->actingAs($buyer)
        ->post('/p/launchkit-main/pay', ['idempotency_key' => 'store-key-1'])
        ->assertRedirect(route('funnel.thankyou', ['slug' => 'launchkit-main']));

    $order = Order::where('buyer_user_id', $buyer->id)->first();
    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($this->ledger->availableBalance($buyer, $this->asset->id)->baseString())->toBe('90000000')
        ->and($this->ledger->availableBalance($this->sellerUser, $this->asset->id)->baseString())->toBe('9000000');
});

it('is idempotent — a re-submit never double-charges', function () {
    $buyer = User::factory()->create();
    creditUser($buyer, $this->asset, '100000000');

    $this->actingAs($buyer)->post('/p/launchkit-main/pay', ['idempotency_key' => 'store-key-2']);
    $this->actingAs($buyer)->post('/p/launchkit-main/pay', ['idempotency_key' => 'store-key-2']);

    expect(Order::where('buyer_user_id', $buyer->id)->count())->toBe(1)
        ->and($this->ledger->availableBalance($buyer, $this->asset->id)->baseString())->toBe('90000000');
});

it('blocks a seller from buying their own product', function () {
    $this->actingAs($this->sellerUser)->get('/p/launchkit-main/pay')
        ->assertOk()
        ->assertSee('your own product', false);

    $this->actingAs($this->sellerUser)
        ->post('/p/launchkit-main/pay', ['idempotency_key' => 'own-key'])
        ->assertSessionHasErrors('pay');
});

it('declines when the buyer has insufficient balance', function () {
    $buyer = User::factory()->create();
    creditUser($buyer, $this->asset, '5000000'); // 5 USDT < 10

    $this->actingAs($buyer)
        ->post('/p/launchkit-main/pay', ['idempotency_key' => 'poor-key'])
        ->assertSessionHasErrors('pay');

    expect(Order::where('buyer_user_id', $buyer->id)->count())->toBe(0);
});
