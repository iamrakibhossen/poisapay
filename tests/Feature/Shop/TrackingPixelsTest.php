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
        'tracking' => [
            'meta' => ['enabled' => true, 'pixel_id' => '123456789012345'],
            'ga4' => ['enabled' => true, 'measurement_id' => 'G-ABCD1234'],
            'tiktok' => ['enabled' => false, 'pixel_id' => 'ABCDEF1234567890'],
            'gtm' => ['enabled' => true, 'container_id' => 'GTM-ABC123'],
        ],
    ]);
});

it('injects the enabled pixels + ViewContent into the public sales page', function () {
    $this->get('/p/launchkit-main')
        ->assertOk()
        ->assertSee("fbq('init',\"123456789012345\")", false)   // Meta enabled
        ->assertSee('gtag/js?id=G-ABCD1234', false)             // GA4 enabled
        ->assertSee('googletagmanager.com/ns.html?id=GTM-ABC123', false) // GTM body noscript
        ->assertSee('"type":"view_content"', false)             // load-time event
        ->assertDontSee('analytics.tiktok.com', false);          // TikTok disabled → nothing
});

it('fires InitiateCheckout on the checkout page', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->get(route('checkout.show', ['product' => $this->product->id]))
        ->assertOk()
        ->assertSee('"type":"initiate_checkout"', false)
        ->assertSee("fbq('init',\"123456789012345\")", false);
});

it('renders no tracking markup when the page has none configured', function () {
    $this->page->update(['tracking' => []]);

    $html = $this->get('/p/launchkit-main')->assertOk()->getContent();

    expect($html)->not->toContain('PoisaPay tracking runtime')
        ->and($html)->not->toContain('fbq(');
});

it('ignores a provider that is enabled with a malformed id', function () {
    $this->page->update(['tracking' => ['meta' => ['enabled' => true, 'pixel_id' => 'bad']]]);

    $this->get('/p/launchkit-main')
        ->assertOk()
        ->assertDontSee('fbq(', false);
});
