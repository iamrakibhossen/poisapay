<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Shop\Actions\SalesPage\CreateSalesPage;
use App\Shop\Actions\SalesPage\SetSalesPageStatus;
use App\Shop\Actions\SalesPage\UpdateSalesPage;
use App\Shop\DTOs\SalesPageData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;
use App\Shop\Services\SalesPageService;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved, 'categories' => [],
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => 'LaunchKit',
        'slug' => 'launchkit', 'status' => ProductStatus::Published,
        'price_amount' => 4900, 'price_asset_id' => $this->asset->id,
    ]);
    $this->pages = app(SalesPageService::class);
});

$page = fn (array $extra = []) => SalesPageData::fromArray(array_merge([
    'product_id' => test()->product->id, 'name' => 'LaunchKit Main',
    'sections' => [['type' => 'hero']], 'theme' => ['accent' => '#2563eb'],
], $extra));

it('creates a draft page with a unique global slug', function () use ($page) {
    $p = app(CreateSalesPage::class)->execute($this->seller, $page());

    expect($p->status)->toBe(SalesPageStatus::Draft)->and($p->slug)->toBe('launchkit-main');
});

it('lets a product have multiple pages', function () use ($page) {
    app(CreateSalesPage::class)->execute($this->seller, $page(['name' => 'Main']));
    app(CreateSalesPage::class)->execute($this->seller, $page(['name' => 'Black Friday']));

    expect($this->product->salesPages()->count())->toBe(2);
});

it('publishes a page and audits it', function () use ($page) {
    $p = app(CreateSalesPage::class)->execute($this->seller, $page());

    app(SetSalesPageStatus::class)->execute($p, SalesPageStatus::Published);

    expect($p->fresh()->status)->toBe(SalesPageStatus::Published)
        ->and($p->fresh()->published_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'shop.sales_page.published')->exists())->toBeTrue();
});

it('serves a cached public view for published pages, null otherwise', function () use ($page) {
    $p = app(CreateSalesPage::class)->execute($this->seller, $page());

    expect($this->pages->publicView($p->slug))->toBeNull(); // draft → not public

    app(SetSalesPageStatus::class)->execute($p, SalesPageStatus::Published);

    $view = $this->pages->publicView($p->slug);
    expect($view['name'])->toBe('LaunchKit Main')
        ->and($view['product']['name'])->toBe('LaunchKit')
        ->and($view['seller']['name'])->toBe($this->seller->displayName());
});

it('invalidates the public cache automatically when the page changes', function () use ($page) {
    $p = app(CreateSalesPage::class)->execute($this->seller, $page());
    app(SetSalesPageStatus::class)->execute($p, SalesPageStatus::Published);

    expect($this->pages->publicView($p->slug)['name'])->toBe('LaunchKit Main'); // warms cache

    app(UpdateSalesPage::class)->execute($p->fresh(), $page(['name' => 'LaunchKit Renamed']));

    $view = $this->pages->publicView($p->slug); // rebuilt after the saved-observer forgot the key
    expect($view['name'])->toBe('LaunchKit Renamed')->and($view['version'])->toBe(2);
});

it('invalidates the page cache when its product changes', function () use ($page) {
    $p = app(CreateSalesPage::class)->execute($this->seller, $page());
    app(SetSalesPageStatus::class)->execute($p, SalesPageStatus::Published);
    $this->pages->publicView($p->slug); // warm

    $this->product->update(['name' => 'LaunchKit Pro']); // Product saved → forgetForProduct

    expect($this->pages->publicView($p->slug)['product']['name'])->toBe('LaunchKit Pro');
});

it('creates a page over the REST API', function () use ($page) {
    $this->actingAs($this->sellerUser)
        ->postJson('/api/shop/sales-pages', [
            'product_id' => $this->product->id, 'name' => 'Campaign Page',
            'sections' => [['type' => 'hero']],
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'campaign-page')
        ->assertJsonPath('data.status', 'draft');
});

it('serves the public page over HTTP only when published', function () use ($page) {
    $p = app(CreateSalesPage::class)->execute($this->seller, $page());

    $this->getJson("/api/shop/public/pages/{$p->slug}")->assertNotFound(); // draft

    app(SetSalesPageStatus::class)->execute($p, SalesPageStatus::Published);

    $this->getJson("/api/shop/public/pages/{$p->slug}")
        ->assertOk()
        ->assertJsonPath('data.product.name', 'LaunchKit');
});

it('forbids editing another seller\'s page', function () use ($page) {
    $p = app(CreateSalesPage::class)->execute($this->seller, $page());

    $this->actingAs(User::factory()->create())
        ->getJson("/api/shop/sales-pages/{$p->id}")
        ->assertForbidden();
});
