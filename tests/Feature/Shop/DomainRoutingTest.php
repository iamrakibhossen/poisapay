<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Actions\Domain\RemoveDomain;
use App\Shop\Actions\Domain\SetDomainDisabled;
use App\Shop\Enums\DomainSslStatus;
use App\Shop\Enums\DomainStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Domain;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Shop\Services\Domain\DomainResolver;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    updateSetting('shop_custom_domains', true);

    $this->asset = testAsset('USDT', 6, 'tron');
    $seller = Seller::create([
        'user_id' => User::factory()->create()->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Acme', 'categories' => [],
    ]);
    $product = Product::create([
        'seller_id' => $seller->id, 'type' => ProductType::Digital, 'name' => 'LaunchKit',
        'slug' => 'launchkit', 'status' => ProductStatus::Published, 'summary' => 'Ship faster',
        'price_amount' => 4900, 'price_asset_id' => $this->asset->id,
    ]);
    $this->page = SalesPage::create([
        'seller_id' => $seller->id, 'product_id' => $product->id, 'name' => 'Main',
        'slug' => 'launchkit-main', 'status' => SalesPageStatus::Published, 'version' => 1,
        'published_at' => now(), 'sections' => [], 'theme' => [],
    ]);
    $this->seller = $seller;

    $this->connect = function (string $host, array $overrides = []) {
        return Domain::create(array_merge([
            'seller_id' => $this->seller->id, 'sales_page_id' => $this->page->id, 'host' => $host,
            'status' => DomainStatus::Verified, 'ssl_status' => DomainSslStatus::Active,
            'verification_token' => 'tok-'.$host, 'verified_at' => now(),
        ], $overrides));
    };
});

it('serves the sales page on a verified custom domain', function () {
    ($this->connect)('store.acme.com');

    $this->get('http://store.acme.com/')
        ->assertOk()
        ->assertSee('LaunchKit');
});

it('serves the same page on the www alias', function () {
    ($this->connect)('store.acme.com');

    $this->get('http://www.store.acme.com/')->assertOk()->assertSee('LaunchKit');
});

it('rewrites sub-paths onto the funnel routes', function () {
    ($this->connect)('store.acme.com');

    // /checkout → /p/{slug}/checkout; a guest is redirected to the account step.
    $this->get('http://store.acme.com/checkout')->assertRedirect();
});

it('passes platform hosts through to normal routing', function () {
    ($this->connect)('store.acme.com');

    // Default test host is the platform host → the slug route still works.
    $this->get('/p/launchkit-main')->assertOk()->assertSee('LaunchKit');
});

it('404s an unknown host', function () {
    $this->get('http://nobody.example.net/')->assertNotFound();
});

it('404s a custom host when the feature is off', function () {
    ($this->connect)('store.acme.com');
    updateSetting('shop_custom_domains', false);

    $this->get('http://store.acme.com/')->assertNotFound();
});

it('does not serve a disabled domain', function () {
    ($this->connect)('store.acme.com', ['disabled_at' => now()]);

    $this->get('http://store.acme.com/')->assertNotFound();
});

it('does not serve a domain whose page is unpublished', function () {
    $this->page->update(['status' => SalesPageStatus::Draft]);
    ($this->connect)('store.acme.com');

    $this->get('http://store.acme.com/')->assertNotFound();
});

it('caches the host lookup', function () {
    ($this->connect)('store.acme.com');
    Cache::forget((new DomainResolver)->cacheKey('store.acme.com'));

    $this->get('http://store.acme.com/')->assertOk();

    expect(Cache::get((new DomainResolver)->cacheKey('store.acme.com')))
        ->toBeArray()
        ->toHaveKey('slug', 'launchkit-main');
});

it('invalidates the cache when a domain is disabled', function () {
    $domain = ($this->connect)('store.acme.com');
    $this->get('http://store.acme.com/')->assertOk();   // warms cache

    app(SetDomainDisabled::class)->execute($domain, true);

    $this->get('http://store.acme.com/')->assertNotFound();
});

it('frees the host after removal', function () {
    $domain = ($this->connect)('store.acme.com');
    $this->get('http://store.acme.com/')->assertOk();

    app(RemoveDomain::class)->execute($domain);

    $this->get('http://store.acme.com/')->assertNotFound();
});
