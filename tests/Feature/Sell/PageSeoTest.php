<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\Actions\Review\SubmitReview;
use App\Shop\DTOs\CheckoutData;
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
        'slug' => 'launchkit', 'status' => ProductStatus::Published, 'summary' => 'Ship faster',
        'price_amount' => 49_000000, 'price_asset_id' => $this->asset->id,
    ]);
    $this->page = SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'name' => 'Main',
        'slug' => 'launchkit-main', 'status' => SalesPageStatus::Published, 'version' => 1, 'published_at' => now(),
        'sections' => [
            ['type' => 'faq', 'enabled' => true, 'content' => [['q' => 'Refunds?', 'a' => '14-day money back.']]],
        ],
        'theme' => [],
    ]);
});

it('renders custom SEO meta + canonical + robots in the head', function () {
    $this->page->update(['seo' => ['title' => 'Custom Title', 'description' => 'Buy this now', 'noindex' => true]]);

    $res = $this->get('/p/launchkit-main')->assertOk();
    $res->assertSee('<title>Custom Title</title>', false)
        ->assertSee('<meta name="description" content="Buy this now">', false)
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('property="og:title" content="Custom Title"', false);
});

it('falls back to product-derived meta when no SEO set', function () {
    $this->get('/p/launchkit-main')->assertOk()
        ->assertSee('<title>LaunchKit · Rahim Studios</title>', false)
        ->assertSee('Ship faster', false)
        ->assertSee('<meta name="robots" content="index,follow">', false);
});

it('emits Product and FAQPage JSON-LD', function () {
    $res = $this->get('/p/launchkit-main')->assertOk();
    $res->assertSee('application/ld+json', false)
        ->assertSee('"@type":"Product"', false)
        ->assertSee('"priceCurrency":"USDT"', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"Question"', false);
});

it('includes AggregateRating once the product has reviews', function () {
    $buyer = User::factory()->create();
    creditUser($buyer, $this->asset, '100000000');
    $order = app(PlaceOrder::class)->execute($buyer, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'quantity' => 1, 'idempotency_key' => 'seo-1',
    ]));
    app(SubmitReview::class)->execute($buyer, $order, $this->product->id, 5, 'Great', 'Loved it');

    $this->get('/p/launchkit-main')->assertOk()
        ->assertSee('"aggregateRating"', false)
        ->assertSee('"reviewCount":1', false);
});

it('persists SEO settings from the builder', function () {
    $this->actingAs($this->sellerUser)
        ->post(route('shop.sales-page.update', ['slug' => 'launchkit-main']), [
            'name' => 'Main', 'builder' => json_encode(['theme' => [], 'sections' => []]),
            'seo_title' => 'My SEO Title', 'seo_description' => 'My description', 'seo_noindex' => '1',
        ])
        ->assertRedirect(route('shop.sales-page.edit', ['slug' => 'launchkit-main']));

    $seo = $this->page->fresh()->seo;
    expect($seo['title'])->toBe('My SEO Title')
        ->and($seo['description'])->toBe('My description')
        ->and($seo['noindex'])->toBeTrue();
});
