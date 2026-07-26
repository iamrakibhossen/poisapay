<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the isolated Landing module — its own routes, views, Blade components and
 * config, kept fully separate from the rest of the app (Shop / Wallet / P2P /
 * Admin / Auth). Nothing here touches shared infrastructure: the module owns the
 * `landing::` view namespace, the `<x-landing.*>` component namespace, its own
 * route file and its own Vite bundle (loaded only inside landing layouts).
 */
final class LandingServiceProvider extends ServiceProvider
{
    private const PATH = __DIR__.'/../../resources/landing';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/landing.php', 'landing');
    }

    public function boot(): void
    {
        // Registers the `landing::` view namespace, which also enables anonymous
        // namespaced Blade components: `<x-landing::navbar>` → `landing::components.navbar`,
        // `<x-landing::layouts.master>` → `landing::components.layouts.master`, etc.
        View::addNamespace('landing', self::PATH.'/views');

        // The Landing module owns its own route file (registered separately from web.php).
        $this->loadRoutesFrom(self::PATH.'/../../routes/landing.php');
    }
}
