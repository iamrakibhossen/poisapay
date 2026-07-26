<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Contracts\DnsResolver;
use App\Shop\Enums\DomainStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Domain;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

use Tests\Support\FakeDnsResolver;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    updateSetting('shop_custom_domains', true);
    Queue::fake();
    app()->instance(DnsResolver::class, new FakeDnsResolver);

    $asset = testAsset('USDT', 6, 'tron');
    $this->user = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->user->id, 'status' => SellerStatus::Approved, 'brand_name' => 'Acme', 'categories' => [],
    ]);
    $product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => 'Kit',
        'slug' => 'kit', 'status' => ProductStatus::Published, 'price_amount' => 4900, 'price_asset_id' => $asset->id,
    ]);
    $this->page = SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $product->id, 'name' => 'Main', 'slug' => 'kit-main',
        'status' => SalesPageStatus::Published, 'version' => 1, 'published_at' => now(), 'sections' => [], 'theme' => [],
    ]);
});

it('renders the merchant domains dashboard with connectable pages', function () {
    actingAs($this->user)->get(route('shop.domains'))
        ->assertOk()
        ->assertSee('Custom domains')
        ->assertSee('Main'); // the page is offered by name in the connect dropdown
});

it('connects a domain over HTTP and shows its DNS records', function () {
    actingAs($this->user)->post(route('shop.domains.store'), [
        'sales_page_id' => $this->page->id, 'host' => 'shop.acme.com',
    ])->assertRedirect();

    $domain = Domain::where('host', 'shop.acme.com')->first();
    expect($domain)->not->toBeNull()->and($domain->status)->toBe(DomainStatus::Pending);

    // Dashboard shows the TXT ownership + CNAME routing records.
    $res = actingAs($this->user)->get(route('shop.domains'))->assertOk();
    $res->assertSee('_poisapay-challenge.shop.acme.com')
        ->assertSee('poisapay-domain-verification='.$domain->verification_token)
        ->assertSee('connect.poisapay.com')
        ->assertSee('CNAME');
});

it('shows a friendly error for an invalid domain', function () {
    actingAs($this->user)->post(route('shop.domains.store'), [
        'sales_page_id' => $this->page->id, 'host' => 'not a domain',
    ])->assertRedirect()->assertSessionHas('error');

    expect(Domain::count())->toBe(0);
});

it('removes a domain over HTTP', function () {
    $domain = Domain::create([
        'seller_id' => $this->seller->id, 'sales_page_id' => $this->page->id, 'host' => 'shop.acme.com',
        'status' => DomainStatus::Verified, 'verification_token' => 'tok', 'verified_at' => now(),
    ]);

    actingAs($this->user)->delete(route('shop.domains.destroy', $domain->id))->assertRedirect();

    expect(Domain::whereKey($domain->id)->exists())->toBeFalse();
});

it("forbids managing another seller's domain", function () {
    $domain = Domain::create([
        'seller_id' => $this->seller->id, 'sales_page_id' => $this->page->id, 'host' => 'shop.acme.com',
        'status' => DomainStatus::Verified, 'verification_token' => 'tok', 'verified_at' => now(),
    ]);
    $intruder = User::factory()->create();

    actingAs($intruder)->delete(route('shop.domains.destroy', $domain->id))->assertForbidden();
    expect(Domain::whereKey($domain->id)->exists())->toBeTrue();
});
