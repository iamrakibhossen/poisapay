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

    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim Studios', 'categories' => [],
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => 'LaunchKit',
        'slug' => 'launchkit', 'status' => ProductStatus::Published,
        'price_amount' => 4900, 'price_asset_id' => $this->asset->id,
    ]);
    $this->page = SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'name' => 'Main',
        'slug' => 'launchkit-main', 'status' => SalesPageStatus::Published, 'version' => 1,
        'published_at' => now(), 'sections' => [], 'theme' => [],
    ]);
});

function saveTracking(array $tracking): array
{
    return [
        'name' => 'Main',
        'tracking' => $tracking,
    ];
}

it('shows the Tracking & Pixels section in the builder', function () {
    $this->actingAs($this->sellerUser)
        ->get(route('shop.sales-page.edit', ['slug' => 'launchkit-main']))
        ->assertOk()
        ->assertSee('Tracking &amp; pixels', false)
        ->assertSee('Meta (Facebook) Pixel')
        ->assertSee('TikTok Pixel')
        ->assertSee('Google Analytics 4')
        ->assertSee('Google Tag Manager');
});

it('persists valid pixel config from the settings form', function () {
    $this->actingAs($this->sellerUser)
        ->post(route('shop.sales-page.update', ['slug' => 'launchkit-main']), saveTracking([
            'meta' => ['enabled' => '1', 'pixel_id' => '123456789012345'],
            'ga4' => ['enabled' => '1', 'measurement_id' => 'G-ABCD1234'],
            'privacy' => ['cookies' => '1', 'consent_required' => '1'],
        ]))
        ->assertRedirect(route('shop.sales-page.edit', ['slug' => 'launchkit-main']));

    $tracking = $this->page->fresh()->tracking;
    expect($tracking['meta']['pixel_id'])->toBe('123456789012345')
        ->and($tracking['ga4']['measurement_id'])->toBe('G-ABCD1234')
        ->and($tracking['privacy']['consent_required'])->toBeTrue()
        ->and($tracking)->not->toHaveKey('tiktok'); // untouched provider not persisted
});

it('rejects a malformed pixel id at validation', function () {
    $this->actingAs($this->sellerUser)
        ->post(route('shop.sales-page.update', ['slug' => 'launchkit-main']), saveTracking([
            'meta' => ['enabled' => '1', 'pixel_id' => 'not-a-pixel'],
        ]))
        ->assertSessionHasErrors('tracking.meta.pixel_id');

    expect($this->page->fresh()->tracking)->toBe([]);
});

it('requires the primary id when a provider is enabled', function () {
    $this->actingAs($this->sellerUser)
        ->post(route('shop.sales-page.update', ['slug' => 'launchkit-main']), saveTracking([
            'gtm' => ['enabled' => '1', 'container_id' => ''],
        ]))
        ->assertSessionHasErrors('tracking.gtm.container_id');
});

it('drops a provider that is switched off even if an id is present', function () {
    $this->actingAs($this->sellerUser)
        ->post(route('shop.sales-page.update', ['slug' => 'launchkit-main']), saveTracking([
            'meta' => ['enabled' => '0', 'pixel_id' => '123456789012345'],
        ]))
        ->assertRedirect();

    expect($this->page->fresh()->tracking)->toBe([]);
});

it('fires a live test event for a configured provider', function () {
    $this->page->update(['tracking' => ['meta' => ['enabled' => true, 'pixel_id' => '123456789012345']]]);

    $this->actingAs($this->sellerUser)
        ->get(route('shop.sales-page.tracking-test', ['slug' => 'launchkit-main']).'?provider=meta')
        ->assertOk()
        ->assertSee("fbq('init',\"123456789012345\")", false)
        ->assertSee('"type":"purchase"', false)
        ->assertSee('Test events sent');
});

it('tells the merchant when the tested provider is not configured', function () {
    $this->actingAs($this->sellerUser)
        ->get(route('shop.sales-page.tracking-test', ['slug' => 'launchkit-main']).'?provider=meta')
        ->assertOk()
        ->assertDontSee('fbq(', false)
        ->assertSee('isn’t configured', false);
});

it('blocks a non-owner from the test endpoint', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('shop.sales-page.tracking-test', ['slug' => 'launchkit-main']).'?provider=meta')
        ->assertForbidden();
});
