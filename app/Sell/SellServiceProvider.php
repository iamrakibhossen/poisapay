<?php

declare(strict_types=1);

namespace App\Sell;

use App\Sell\Contracts\AuditableEvent;
use App\Sell\Listeners\AuditSellEvent;
use App\Sell\Models\Product;
use App\Sell\Models\SalesPage;
use App\Sell\Models\Seller;
use App\Sell\Policies\ProductPolicy;
use App\Sell\Policies\SalesPagePolicy;
use App\Sell\Policies\SellerPolicy;
use App\Sell\Services\SalesPageService;
use App\Sell\Services\SellerService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the isolated Sell module: event→listener bindings,
 * policies, cache invalidation, and route loading.
 */
class SellServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private array $policies = [
        Seller::class => SellerPolicy::class,
        Product::class => ProductPolicy::class,
        SalesPage::class => SalesPagePolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Audit everything: every AuditableEvent is recorded via the core Audit module.
        Event::listen(AuditableEvent::class, AuditSellEvent::class);

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
