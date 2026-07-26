<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
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

it('hands a signed-in buyer straight to the central pay page (no CSRF token needed)', function () {
    $buyer = User::factory()->create();

    // Cross-origin handoff: no CSRF token, only the page slug.
    $this->actingAs($buyer)
        ->post('/checkout', ['slug' => 'launchkit-main'])
        ->assertRedirect(route('funnel.pay', ['slug' => 'launchkit-main']));
});

it('sends a guest through the express-account step first, then resumes checkout', function () {
    $this->post('/checkout', ['slug' => 'launchkit-main'])
        ->assertRedirect(route('funnel.account', ['slug' => 'launchkit-main']));

    expect(session('url.intended'))->toBe(route('funnel.pay', ['slug' => 'launchkit-main']));
});

it('carries a coupon through the handoff', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->post('/checkout', ['slug' => 'launchkit-main', 'coupon' => 'SAVE10'])
        ->assertRedirect(route('funnel.pay', ['slug' => 'launchkit-main']).'?coupon=SAVE10');
});

it('404s an unknown or unpublished page', function () {
    $this->post('/checkout', ['slug' => 'nope'])->assertNotFound();

    $this->page->update(['status' => SalesPageStatus::Draft]);
    User::factory()->create();
    $this->post('/checkout', ['slug' => 'launchkit-main'])->assertNotFound();
});

it('exposes a shareable product-keyed direct checkout link', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->get('/checkout/'.$this->product->id)
        ->assertRedirect(route('funnel.pay', ['slug' => 'launchkit-main']));
});

it('returns the buyer to the originating storefront via the back link', function () {
    $buyer = User::factory()->create();
    $storefront = rtrim((string) config('app.url'), '/').'/p/launchkit-main';

    $this->actingAs($buyer)->post('/checkout', ['slug' => 'launchkit-main', 'return_url' => $storefront]);

    $this->actingAs($buyer)->get(route('funnel.pay', ['slug' => 'launchkit-main']))
        ->assertOk()
        ->assertSee($storefront, false);
});

it('ignores an untrusted return_url (anti open-redirect)', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->post('/checkout', ['slug' => 'launchkit-main', 'return_url' => 'https://evil.example/phish']);

    $this->actingAs($buyer)->get(route('funnel.pay', ['slug' => 'launchkit-main']))
        ->assertOk()
        ->assertDontSee('evil.example');
});

it('the storefront Buy form posts to the central platform-host checkout', function () {
    $host = rtrim((string) config('app.url'), '/');

    $this->get('/p/launchkit-main')
        ->assertOk()
        ->assertSee($host.'/checkout', false)
        ->assertSee('name="slug" value="launchkit-main"', false);
});
