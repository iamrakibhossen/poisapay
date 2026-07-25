<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Shop\Actions\Product\CreateProduct;
use App\Shop\Actions\Product\SetProductStatus;
use App\Shop\Actions\Product\UpdateProduct;
use App\Shop\DTOs\ProductData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;
use App\Shop\Services\CatalogService;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->user = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->user->id, 'status' => SellerStatus::Approved, 'categories' => [],
    ]);
});

$product = fn (array $overrides = []) => ProductData::fromArray(array_merge([
    'type' => 'digital',
    'name' => 'LaunchKit — Laravel SaaS Boilerplate',
    'summary' => 'Ship your SaaS in a weekend.',
    'price_amount' => 4900,
    'price_asset_id' => test()->asset->id,
], $overrides));

it('creates a draft product with a generated slug and audits it', function () use ($product) {
    $p = app(CreateProduct::class)->execute($this->seller, $product());

    expect($p->status)->toBe(ProductStatus::Draft)
        ->and($p->slug)->toBe('launchkit-laravel-saas-boilerplate')
        ->and($p->type)->toBe(ProductType::Digital)
        ->and(AuditLog::where('action', 'shop.product.created')->exists())->toBeTrue();
});

it('generates unique slugs per seller', function () use ($product) {
    $a = app(CreateProduct::class)->execute($this->seller, $product(['name' => 'UI Kit']));
    $b = app(CreateProduct::class)->execute($this->seller, $product(['name' => 'UI Kit']));

    expect($a->slug)->toBe('ui-kit')->and($b->slug)->toBe('ui-kit-2');
});

it('creates a physical product with a variant matrix', function () use ($product) {
    $p = app(CreateProduct::class)->execute($this->seller, $product([
        'type' => 'physical',
        'name' => 'Dev Tee',
        'variants' => [
            ['options' => ['Size' => 'M', 'Color' => 'Black'], 'stock' => 10, 'price_amount' => 2500],
            ['options' => ['Size' => 'L', 'Color' => 'Black'], 'stock' => 5, 'price_amount' => 2500],
        ],
    ]));

    expect($p->has_variants)->toBeTrue()
        ->and($p->requires_shipping)->toBeTrue()
        ->and($p->variants()->where('is_active', true)->count())->toBe(2);
});

it('publishes a product and stamps published_at', function () use ($product) {
    $p = app(CreateProduct::class)->execute($this->seller, $product());

    app(SetProductStatus::class)->execute($p, ProductStatus::Published);

    expect($p->fresh()->status)->toBe(ProductStatus::Published)
        ->and($p->fresh()->published_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'shop.product.published')->exists())->toBeTrue();
});

it('refuses to publish a variant product with no active variants', function () use ($product) {
    $p = app(CreateProduct::class)->execute($this->seller, $product());
    $p->update(['has_variants' => true]); // variant product with zero variants

    expect(fn () => app(SetProductStatus::class)->execute($p->fresh(), ProductStatus::Published))
        ->toThrow(ShopException::class);
});

it('finds products by full-text search, scoped to the seller', function () use ($product) {
    app(CreateProduct::class)->execute($this->seller, $product(['name' => 'LaunchKit Boilerplate']));
    app(CreateProduct::class)->execute($this->seller, $product(['name' => 'Premium UI Kit']));

    $hits = app(CatalogService::class)->search($this->seller, 'launchkit');

    expect($hits->count())->toBe(1)
        ->and($hits->first()->name)->toBe('LaunchKit Boilerplate');
});

it('updates a product but keeps the slug stable', function () use ($product) {
    $p = app(CreateProduct::class)->execute($this->seller, $product());
    $slug = $p->slug;

    app(UpdateProduct::class)->execute($p, $product(['name' => 'LaunchKit Pro', 'price_amount' => 9900]));

    expect($p->fresh()->name)->toBe('LaunchKit Pro')
        ->and($p->fresh()->price_amount)->toBe(9900)
        ->and($p->fresh()->slug)->toBe($slug); // unchanged
});

it('blocks a non-approved seller from creating products', function () use ($product) {
    $this->seller->update(['status' => SellerStatus::PendingReview]);

    expect(fn () => app(CreateProduct::class)->execute($this->seller->fresh(), $product()))
        ->toThrow(ShopException::class);
});

it('creates a product over the REST API and returns a resource', function () {
    $this->actingAs($this->user)
        ->postJson('/api/shop/products', [
            'type' => 'digital', 'name' => 'API Product',
            'price_amount' => 1000, 'price_asset_id' => $this->asset->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'api-product')
        ->assertJsonPath('data.status', 'draft');
});

it('forbids viewing another seller\'s product (no ID enumeration)', function () use ($product) {
    $p = app(CreateProduct::class)->execute($this->seller, $product());

    $this->actingAs(User::factory()->create())
        ->getJson("/api/shop/products/{$p->id}")
        ->assertForbidden();
});
