<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Enums\OrderStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Order;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');

    $seller = Seller::create([
        'user_id' => User::factory()->create()->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Acme', 'categories' => [],
    ]);
    $this->product = Product::create([
        'seller_id' => $seller->id, 'type' => ProductType::Digital, 'name' => 'LaunchKit',
        'slug' => 'launchkit', 'status' => ProductStatus::Published,
        'price_amount' => 4900, 'price_asset_id' => $this->asset->id,
    ]);
    $this->page = SalesPage::create([
        'seller_id' => $seller->id, 'product_id' => $this->product->id, 'name' => 'Main',
        'slug' => 'launchkit-main', 'status' => SalesPageStatus::Published, 'version' => 1,
        'published_at' => now(), 'sections' => [], 'theme' => [],
    ]);
});

it('hands a signed-in buyer to the product-keyed central checkout (no CSRF token needed)', function () {
    $buyer = User::factory()->create();

    // Cross-origin handoff: no CSRF token, only the page slug → /checkout/{product}.
    $this->actingAs($buyer)
        ->post('/checkout', ['slug' => 'launchkit-main'])
        ->assertRedirect(route('checkout.show', ['product' => $this->product->id]));
});

it('sends a guest through the express-account step first, then resumes checkout', function () {
    $this->post('/checkout', ['slug' => 'launchkit-main'])
        ->assertRedirect(route('funnel.account', ['slug' => 'launchkit-main']));

    expect(session('url.intended'))->toBe(route('checkout.show', ['product' => $this->product->id]));
});

it('carries a coupon through the handoff', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->post('/checkout', ['slug' => 'launchkit-main', 'coupon' => 'SAVE10'])
        ->assertRedirect(route('checkout.show', ['product' => $this->product->id]).'?coupon=SAVE10');
});

it('404s an unknown or unpublished page', function () {
    $this->post('/checkout', ['slug' => 'nope'])->assertNotFound();

    $this->page->update(['status' => SalesPageStatus::Draft]);
    User::factory()->create();
    $this->post('/checkout', ['slug' => 'launchkit-main'])->assertNotFound();
});

it('renders the checkout at the product-keyed /checkout/{product} url', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->get(route('checkout.show', ['product' => $this->product->id]))
        ->assertOk()
        ->assertSee('Total')
        // The pay form posts back to the product-keyed central route.
        ->assertSee(route('checkout.pay', ['product' => $this->product->id]), false);
});

it('returns the buyer to the originating storefront via the back link', function () {
    $buyer = User::factory()->create();
    $storefront = rtrim((string) config('app.url'), '/').'/p/launchkit-main';

    $this->actingAs($buyer)->post('/checkout', ['slug' => 'launchkit-main', 'return_url' => $storefront]);

    $this->actingAs($buyer)->get(route('checkout.show', ['product' => $this->product->id]))
        ->assertOk()
        ->assertSee($storefront, false);
});

it('ignores an untrusted return_url (anti open-redirect)', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->post('/checkout', ['slug' => 'launchkit-main', 'return_url' => 'https://evil.example/phish']);

    $this->actingAs($buyer)->get(route('checkout.show', ['product' => $this->product->id]))
        ->assertOk()
        ->assertDontSee('evil.example');
});

it('places a real order through the central checkout and lands on the central thank-you', function () {
    $buyer = User::factory()->create();
    creditUser($buyer, $this->asset, '1000000'); // 1 USDT — plenty for the 0.0049 product

    $this->actingAs($buyer)
        ->post(route('checkout.pay', ['product' => $this->product->id]), ['idempotency_key' => 'central-1'])
        ->assertRedirect(route('checkout.thankyou', ['product' => $this->product->id]));

    expect(Order::where('buyer_user_id', $buyer->id)->where('status', OrderStatus::Paid)->exists())->toBeTrue();
});

it('the storefront Buy form posts to the central platform-host checkout', function () {
    $host = rtrim((string) config('app.url'), '/');

    $this->get('/p/launchkit-main')
        ->assertOk()
        ->assertSee($host.'/checkout', false)
        ->assertSee('name="slug" value="launchkit-main"', false);
});
