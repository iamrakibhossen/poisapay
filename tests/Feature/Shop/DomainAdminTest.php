<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Shop\Enums\DomainSslStatus;
use App\Shop\Enums\DomainStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Jobs\VerifyDomainJob;
use App\Shop\Models\Domain;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    updateSetting('shop_enabled', true);
    updateSetting('shop_custom_domains', true);

    $this->admin = Admin::create([
        'name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->admin->syncRoles(['super-admin']);

    $asset = testAsset('USDT', 6, 'tron');
    $seller = Seller::create([
        'user_id' => User::factory()->create(['name' => 'Rahim'])->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Acme', 'categories' => [],
    ]);
    $product = Product::create([
        'seller_id' => $seller->id, 'type' => ProductType::Digital, 'name' => 'Kit',
        'slug' => 'kit', 'status' => ProductStatus::Published, 'price_amount' => 4900, 'price_asset_id' => $asset->id,
    ]);
    $page = SalesPage::create([
        'seller_id' => $seller->id, 'product_id' => $product->id, 'name' => 'Main', 'slug' => 'kit-main',
        'status' => SalesPageStatus::Published, 'version' => 1, 'published_at' => now(), 'sections' => [], 'theme' => [],
    ]);
    $this->domain = Domain::create([
        'seller_id' => $seller->id, 'sales_page_id' => $page->id, 'host' => 'store.acme.com',
        'status' => DomainStatus::Verified, 'ssl_status' => DomainSslStatus::Active,
        'verification_token' => 'tok', 'verified_at' => now(),
    ]);
});

it('lists domains for an operator', function () {
    actingAs($this->admin, 'admin')->get(route('admin.shop-domains'))
        ->assertOk()
        ->assertSee('store.acme.com')
        ->assertSee('Rahim');
});

it('filters domains by search', function () {
    actingAs($this->admin, 'admin')->get(route('admin.shop-domains', ['search' => 'nomatch']))
        ->assertOk()
        ->assertDontSee('store.acme.com');
});

it('disables a domain', function () {
    actingAs($this->admin, 'admin')
        ->post(route('admin.shop-domains.disable', $this->domain->id))
        ->assertRedirect();

    expect($this->domain->fresh()->isDisabled())->toBeTrue();
    expect(AuditLog::where('action', 'shop.domain.disabled')->exists())->toBeTrue();
});

it('re-enables a domain and re-queues verification', function () {
    Queue::fake();
    $this->domain->forceFill(['disabled_at' => now()])->save();

    actingAs($this->admin, 'admin')
        ->post(route('admin.shop-domains.enable', $this->domain->id))
        ->assertRedirect();

    expect($this->domain->fresh()->isDisabled())->toBeFalse();
    Queue::assertPushed(VerifyDomainJob::class);
});

it('re-verifies a domain on demand', function () {
    Queue::fake();

    actingAs($this->admin, 'admin')
        ->post(route('admin.shop-domains.reverify', $this->domain->id))
        ->assertRedirect();

    expect($this->domain->fresh()->status)->toBe(DomainStatus::Verifying);
    Queue::assertPushed(VerifyDomainJob::class);
});

it('forbids a non-privileged operator', function () {
    $plain = Admin::create([
        'name' => 'Plain', 'email' => 'plain@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);

    actingAs($plain, 'admin')->get(route('admin.shop-domains'))->assertForbidden();
});
