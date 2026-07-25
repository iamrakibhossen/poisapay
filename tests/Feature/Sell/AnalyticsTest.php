<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\AnalyticsEvent;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Shop\Services\AnalyticsService;

beforeEach(function () {
    updateSetting('sell_enabled', true);
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
    $this->page = SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'name' => 'Main',
        'slug' => 'launchkit-main', 'status' => SalesPageStatus::Published,
        'sections' => [], 'theme' => [], 'version' => 1, 'published_at' => now(),
    ]);
});

it('records a deduplicated page view when the public page is visited', function () {
    $this->get('/p/launchkit-main')->assertOk();
    $this->get('/p/launchkit-main')->assertOk(); // same session → still one view

    expect(AnalyticsEvent::where('type', 'page_view')->count())->toBe(1)
        ->and(AnalyticsEvent::first()->sales_page_id)->toBe($this->page->id);
});

it('records checkout_start and purchase across the funnel', function () {
    $buyer = User::factory()->create();
    creditUser($buyer, $this->asset, '100000000');

    $this->actingAs($buyer)->get('/p/launchkit-main/checkout')->assertOk();
    $this->actingAs($buyer)->post('/p/launchkit-main/checkout', ['idempotency_key' => 'an-1'])
        ->assertRedirect(route('funnel.thankyou', ['slug' => 'launchkit-main']));

    expect(AnalyticsEvent::where('type', 'checkout_start')->count())->toBe(1)
        ->and(AnalyticsEvent::where('type', 'purchase')->count())->toBe(1)
        ->and(AnalyticsEvent::where('type', 'purchase')->first()->order_id)->not->toBeNull();
});

it('shows real KPIs on the seller analytics page', function () {
    // Two visitors, one converting.
    AnalyticsEvent::create(['seller_id' => $this->seller->id, 'sales_page_id' => $this->page->id, 'type' => 'page_view', 'session_id' => 'a', 'occurred_at' => now()]);
    AnalyticsEvent::create(['seller_id' => $this->seller->id, 'sales_page_id' => $this->page->id, 'type' => 'page_view', 'session_id' => 'b', 'occurred_at' => now()]);
    AnalyticsEvent::create(['seller_id' => $this->seller->id, 'sales_page_id' => $this->page->id, 'type' => 'checkout_start', 'session_id' => 'a', 'occurred_at' => now()]);
    AnalyticsEvent::create(['seller_id' => $this->seller->id, 'sales_page_id' => $this->page->id, 'type' => 'purchase', 'session_id' => 'a', 'occurred_at' => now()]);

    $this->actingAs($this->sellerUser)->get(route('shop.analytics'))
        ->assertOk()
        ->assertSee('50%')   // 1 purchase / 2 visitors conversion
        ->assertSee('Page views');
});

it('buckets traffic sources from referrer and utm', function () {
    AnalyticsEvent::create(['seller_id' => $this->seller->id, 'sales_page_id' => $this->page->id, 'type' => 'page_view', 'session_id' => 'a', 'referrer' => 'https://www.facebook.com/', 'occurred_at' => now()]);
    AnalyticsEvent::create(['seller_id' => $this->seller->id, 'sales_page_id' => $this->page->id, 'type' => 'page_view', 'session_id' => 'b', 'utm' => ['source' => 'newsletter'], 'occurred_at' => now()]);

    $this->actingAs($this->sellerUser)->get(route('shop.analytics'))
        ->assertOk()->assertSee('Facebook')->assertSee('Newsletter');
});
