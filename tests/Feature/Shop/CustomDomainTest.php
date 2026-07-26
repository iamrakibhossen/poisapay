<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Shop\Actions\Domain\ConnectDomain;
use App\Shop\Actions\Domain\ProvisionSsl;
use App\Shop\Actions\Domain\RemoveDomain;
use App\Shop\Actions\Domain\VerifyDomain;
use App\Shop\Contracts\DnsResolver;
use App\Shop\Contracts\SslProvisioner;
use App\Shop\Enums\DnsRecordType;
use App\Shop\Enums\DomainSslStatus;
use App\Shop\Enums\DomainStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Exceptions\DomainException;
use App\Shop\Jobs\ProvisionSslJob;
use App\Shop\Jobs\VerifyDomainJob;
use App\Shop\Models\Domain;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Shop\Services\Ssl\AcmeSslProvisioner;
use App\Shop\Services\Ssl\SimulatedSslProvisioner;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeDnsResolver;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    updateSetting('shop_custom_domains', true);

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

    $this->makePage = function (string $slug = 'launchkit-main'): SalesPage {
        return SalesPage::create([
            'seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'name' => 'Main',
            'slug' => $slug, 'status' => SalesPageStatus::Published, 'version' => 1,
            'published_at' => now(), 'sections' => [], 'theme' => [],
        ]);
    };

    $this->dns = new FakeDnsResolver;
    app()->instance(DnsResolver::class, $this->dns);

    // Seed correct DNS (TXT ownership + CNAME routing) so a host verifies.
    $this->seedGoodDns = function (Domain $d): void {
        $cfg = config('shop.custom_domains');
        $this->dns->setTxt($cfg['txt_name'].'.'.$d->host, [$cfg['txt_prefix'].$d->verification_token]);
        $this->dns->setCname($d->host, [$cfg['cname_target']]);
    };
});

/* ─── Connect ─────────────────────────────────────────────────────────── */

it('connects a domain: normalizes host, mints a token, fires event, queues verify', function () {
    Queue::fake();
    $page = ($this->makePage)();

    $domain = app(ConnectDomain::class)->execute($this->seller, $page, 'HTTP://WWW.MyBrand.COM/');

    expect($domain->host)->toBe('mybrand.com')
        ->and($domain->status)->toBe(DomainStatus::Pending)
        ->and($domain->ssl_status)->toBe(DomainSslStatus::Pending)
        ->and($domain->verification_token)->not->toBeEmpty();

    Queue::assertPushed(VerifyDomainJob::class);
    expect(AuditLog::where('action', 'shop.domain.created')->exists())->toBeTrue();
});

it('rejects an invalid domain', function () {
    $page = ($this->makePage)();
    app(ConnectDomain::class)->execute($this->seller, $page, 'not a domain');
})->throws(DomainException::class);

it('rejects a reserved domain', function () {
    $page = ($this->makePage)();
    app(ConnectDomain::class)->execute($this->seller, $page, 'foo.local');
})->throws(DomainException::class);

it('rejects a platform domain', function () {
    $page = ($this->makePage)();
    app(ConnectDomain::class)->execute($this->seller, $page, 'shop.poisapay.com');
})->throws(DomainException::class);

it('rejects a duplicate host across shops', function () {
    Queue::fake();
    $pageA = ($this->makePage)('page-a');
    $pageB = ($this->makePage)('page-b');
    app(ConnectDomain::class)->execute($this->seller, $pageA, 'store.acme.com');

    app(ConnectDomain::class)->execute($this->seller, $pageB, 'store.acme.com');
})->throws(DomainException::class);

it('rejects a second domain on the same page', function () {
    Queue::fake();
    $page = ($this->makePage)();
    app(ConnectDomain::class)->execute($this->seller, $page, 'one.acme.com');

    app(ConnectDomain::class)->execute($this->seller, $page, 'two.acme.com');
})->throws(DomainException::class);

it('refuses to connect a page owned by another seller', function () {
    $otherUser = User::factory()->create();
    $other = Seller::create(['user_id' => $otherUser->id, 'status' => SellerStatus::Approved, 'categories' => []]);
    $page = ($this->makePage)();

    app(ConnectDomain::class)->execute($other, $page, 'x.acme.com');
})->throws(DomainException::class);

it('refuses to connect when the feature is off', function () {
    updateSetting('shop_custom_domains', false);
    $page = ($this->makePage)();

    app(ConnectDomain::class)->execute($this->seller, $page, 'x.acme.com');
})->throws(DomainException::class);

/* ─── Verification ────────────────────────────────────────────────────── */

it('verifies via CNAME: marks verified, records the record type, queues SSL', function () {
    Queue::fake();
    $page = ($this->makePage)();
    $domain = app(ConnectDomain::class)->execute($this->seller, $page, 'store.acme.com');
    ($this->seedGoodDns)($domain);

    app(VerifyDomain::class)->execute($domain);

    $domain->refresh();
    expect($domain->status)->toBe(DomainStatus::Verified)
        ->and($domain->dns_record_type)->toBe(DnsRecordType::Cname)
        ->and($domain->verified_at)->not->toBeNull()
        ->and($domain->last_error)->toBeNull();

    Queue::assertPushed(ProvisionSslJob::class);
    expect(AuditLog::where('action', 'shop.domain.verified')->exists())->toBeTrue();
});

it('does not verify when only an A record points at us (CNAME-only)', function () {
    Queue::fake();
    $page = ($this->makePage)();
    $domain = app(ConnectDomain::class)->execute($this->seller, $page, 'acme.com');
    // Ownership present, but routing is an A record — not accepted.
    $cfg = config('shop.custom_domains');
    $this->dns->setTxt($cfg['txt_name'].'.'.$domain->host, [$cfg['txt_prefix'].$domain->verification_token]);
    $this->dns->setA($domain->host, ['76.76.21.21']);

    app(VerifyDomain::class)->execute($domain);

    expect($domain->refresh()->status)->toBe(DomainStatus::Failed);
});

it('fails verification when the ownership TXT is missing', function () {
    Queue::fake();
    $page = ($this->makePage)();
    $domain = app(ConnectDomain::class)->execute($this->seller, $page, 'store.acme.com');
    // routing present, ownership absent
    $this->dns->setCname($domain->host, [config('shop.custom_domains.cname_target')]);

    app(VerifyDomain::class)->execute($domain);

    $domain->refresh();
    expect($domain->status)->toBe(DomainStatus::Failed)
        ->and($domain->last_error)->toContain('Ownership');
    Queue::assertPushed(VerifyDomainJob::class); // auto-retry queued
});

it('fails verification when routing is missing', function () {
    Queue::fake();
    $page = ($this->makePage)();
    $domain = app(ConnectDomain::class)->execute($this->seller, $page, 'store.acme.com');
    $cfg = config('shop.custom_domains');
    $this->dns->setTxt($cfg['txt_name'].'.'.$domain->host, [$cfg['txt_prefix'].$domain->verification_token]);

    app(VerifyDomain::class)->execute($domain);

    expect($domain->refresh()->status)->toBe(DomainStatus::Failed)
        ->and($domain->last_error)->toContain('pointing');
});

it('stops retrying once the attempt ceiling is reached', function () {
    $page = ($this->makePage)();
    // Build the domain directly (no connect) so no verify job is pre-queued.
    $domain = Domain::create([
        'seller_id' => $this->seller->id, 'sales_page_id' => $page->id, 'host' => 'store.acme.com',
        'status' => DomainStatus::Failed, 'ssl_status' => DomainSslStatus::Pending,
        'verification_token' => 'tok', 'verify_attempts' => config('shop.custom_domains.verify_max_attempts') - 1,
    ]);

    Queue::fake();
    app(VerifyDomain::class)->execute($domain); // nothing seeded → fails, now exhausted

    Queue::assertNotPushed(VerifyDomainJob::class);
});

/* ─── SSL ─────────────────────────────────────────────────────────────── */

it('provisions SSL (simulated) and marks it active', function () {
    Queue::fake();
    app()->bind(SslProvisioner::class, SimulatedSslProvisioner::class);
    $page = ($this->makePage)();
    $domain = app(ConnectDomain::class)->execute($this->seller, $page, 'store.acme.com');
    ($this->seedGoodDns)($domain);
    app(VerifyDomain::class)->execute($domain);

    app(ProvisionSsl::class)->execute($domain->refresh());

    expect($domain->refresh()->ssl_status)->toBe(DomainSslStatus::Active);
    expect(AuditLog::where('action', 'shop.domain.ssl_issued')->exists())->toBeTrue();
});

it('records SSL failure and retries when issuance throws', function () {
    Queue::fake();
    app()->bind(SslProvisioner::class, AcmeSslProvisioner::class); // stub throws
    $page = ($this->makePage)();
    $domain = app(ConnectDomain::class)->execute($this->seller, $page, 'store.acme.com');
    ($this->seedGoodDns)($domain);
    app(VerifyDomain::class)->execute($domain);

    app(ProvisionSsl::class)->execute($domain->refresh());

    expect($domain->refresh()->ssl_status)->toBe(DomainSslStatus::Failed);
    Queue::assertPushed(ProvisionSslJob::class);
});

it('will not provision SSL for an unverified domain', function () {
    Queue::fake();
    app()->bind(SslProvisioner::class, SimulatedSslProvisioner::class);
    $page = ($this->makePage)();
    $domain = app(ConnectDomain::class)->execute($this->seller, $page, 'store.acme.com');

    app(ProvisionSsl::class)->execute($domain);

    expect($domain->refresh()->ssl_status)->toBe(DomainSslStatus::Pending);
});

/* ─── Removal ─────────────────────────────────────────────────────────── */

it('removes a domain and frees the host', function () {
    Queue::fake();
    $page = ($this->makePage)();
    $domain = app(ConnectDomain::class)->execute($this->seller, $page, 'store.acme.com');

    app(RemoveDomain::class)->execute($domain);

    expect(Domain::where('host', 'store.acme.com')->exists())->toBeFalse();
    expect(AuditLog::where('action', 'shop.domain.removed')->exists())->toBeTrue();
});
