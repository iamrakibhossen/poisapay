<?php

declare(strict_types=1);

namespace App\Shop;

use App\Shop\Builder\BlockLibrary;
use App\Shop\Builder\BlockRegistry;
use App\Shop\Contracts\AuditableEvent;
use App\Shop\Listeners\AuditShopEvent;
use App\Shop\Models\Product;
use App\Shop\Models\RefundRequest;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Shop\Policies\ProductPolicy;
use App\Shop\Policies\RefundRequestPolicy;
use App\Shop\Policies\SalesPagePolicy;
use App\Shop\Policies\SellerPolicy;
use App\Shop\Services\SalesPageService;
use App\Shop\Services\SellerService;
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
    ];

    public function register(): void
    {
        // The block catalogue is a process-wide singleton: one source of truth for
        // the palette, generated property panel, validation, and render dispatch.
        $this->app->singleton(BlockRegistry::class, fn () => new BlockRegistry(BlockLibrary::all()));
    }

    public function boot(): void
    {
        // Audit everything: every AuditableEvent is recorded via the core Audit module.
        Event::listen(AuditableEvent::class, AuditShopEvent::class);

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

        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }
}
