<?php

declare(strict_types=1);

namespace App\Shop;

use App\Shop\Builder\BlockLibrary;
use App\Shop\Builder\BlockRegistry;
use App\Shop\Contracts\AuditableEvent;
use App\Shop\Contracts\DnsResolver;
use App\Shop\Contracts\SslProvisioner;
use App\Shop\Events\OrderPlaced;
use App\Shop\Events\SellerApplied;
use App\Shop\Listeners\AuditShopEvent;
use App\Shop\Listeners\NotifyOperatorsOfSellerApplication;
use App\Shop\Listeners\SendMetaCapiPurchaseEvent;
use App\Shop\Listeners\ShopNotificationSubscriber;
use App\Shop\Models\Domain;
use App\Shop\Models\Product;
use App\Shop\Models\RefundRequest;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Shop\Models\ShopMedia;
use App\Shop\Policies\DomainPolicy;
use App\Shop\Policies\MediaPolicy;
use App\Shop\Policies\ProductPolicy;
use App\Shop\Policies\RefundRequestPolicy;
use App\Shop\Policies\SalesPagePolicy;
use App\Shop\Policies\SellerPolicy;
use App\Shop\Services\Dns\SystemDnsResolver;
use App\Shop\Services\Domain\DomainResolver;
use App\Shop\Services\Media\MediaUrlService;
use App\Shop\Services\SalesPageService;
use App\Shop\Services\SellerService;
use App\Shop\Services\Ssl\AcmeSslProvisioner;
use App\Shop\Services\Ssl\SimulatedSslProvisioner;
use App\Shop\Tracking\Capi\HttpMetaCapiClient;
use App\Shop\Tracking\Capi\SimulatedMetaCapiClient;
use App\Shop\Tracking\Contracts\MetaCapiClient;
use App\Shop\Tracking\Providers\Ga4Provider;
use App\Shop\Tracking\Providers\GtmProvider;
use App\Shop\Tracking\Providers\MetaProvider;
use App\Shop\Tracking\Providers\TikTokProvider;
use App\Shop\Tracking\TrackingManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the isolated Sell module: event→listener bindings,
 * policies, cache invalidation, and route loading.
 */
class ShopServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private array $policies = [
        Seller::class => SellerPolicy::class,
        Product::class => ProductPolicy::class,
        SalesPage::class => SalesPagePolicy::class,
        RefundRequest::class => RefundRequestPolicy::class,
        Domain::class => DomainPolicy::class,
        ShopMedia::class => MediaPolicy::class,
    ];

    public function register(): void
    {
        // The block catalogue is a process-wide singleton: one source of truth for
        // the palette, generated property panel, validation, and render dispatch.
        $this->app->singleton(BlockRegistry::class, fn () => new BlockRegistry(BlockLibrary::all()));

        // Per-sales-page tracking/pixels. Adding a network = one new provider here.
        $this->app->singleton(TrackingManager::class, fn () => new TrackingManager([
            new MetaProvider,
            new TikTokProvider,
            new Ga4Provider,
            new GtmProvider,
        ]));

        // One URL service per request so its URL→media memo (on top of the cache) is
        // shared across every image rendered on a page.
        $this->app->singleton(MediaUrlService::class);

        // Meta Conversions API: simulated (no network) by default, real HTTP in prod.
        $this->app->bind(MetaCapiClient::class, fn () => match (config('shop.tracking.meta_capi.driver')) {
            'http' => new HttpMetaCapiClient,
            default => new SimulatedMetaCapiClient,
        });

        // Custom-domain DNS + SSL sit behind contracts so tests swap in fakes and
        // the SSL provider is chosen by config (simulated by default, ACME in prod).
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
        $this->app->bind(SslProvisioner::class, fn () => match (config('shop.custom_domains.ssl.driver')) {
            'acme' => new AcmeSslProvisioner,
            default => new SimulatedSslProvisioner,
        });
    }

    public function boot(): void
    {
        // Audit everything: every AuditableEvent is recorded via the core Audit module.
        Event::listen(AuditableEvent::class, AuditShopEvent::class);

        // Alert operators when a seller applies so the application can be reviewed.
        Event::listen(SellerApplied::class, NotifyOperatorsOfSellerApplication::class);

        // A paid order queues the server-side Meta Purchase (only if the page opted
        // into CAPI). Deduped with the browser pixel via the shared event_id.
        Event::listen(OrderPlaced::class, SendMetaCapiPurchaseEvent::class);

        // All Shop user-notifications route through one subscriber → NotificationService
        // (templates + per-category channel preferences + broadcast). See the subscriber.
        Event::subscribe(ShopNotificationSubscriber::class);

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Automatic cache invalidation — no manual cache clearing anywhere.
        Seller::saved(fn (Seller $seller) => app(SellerService::class)->forget($seller->user_id));
        Seller::deleted(fn (Seller $seller) => app(SellerService::class)->forget($seller->user_id));

        // A sales-page change drops its own public cache; a product change drops
        // the cache of every sales page that renders it.
        SalesPage::saved(fn (SalesPage $page) => app(SalesPageService::class)->forget($page));
        SalesPage::deleted(fn (SalesPage $page) => app(SalesPageService::class)->forget($page));
        Product::saved(fn (Product $product) => app(SalesPageService::class)->forgetForProduct($product));

        // Any domain write (status, ssl, disable, removal) drops its routing-cache
        // entry so the host resolves to fresh state on the next request.
        Domain::saved(fn (Domain $domain) => app(DomainResolver::class)->forget($domain));
        Domain::deleted(fn (Domain $domain) => app(DomainResolver::class)->forget($domain));

        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }
}
