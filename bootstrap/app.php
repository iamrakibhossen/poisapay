<?php

use App\Http\Middleware\EnsureOperator;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\WebhookLogger;
use App\Shop\Http\Middleware\ResolveShopDomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'operator' => EnsureOperator::class,
            'webhook.log' => WebhookLogger::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Global (pre-routing) so it can rewrite a custom domain onto the funnel
        // routes. Appended → runs after the framework's proxy/host middleware, so
        // Request::getHost() is already the real, proxy-resolved host.
        $middleware->append(ResolveShopDomain::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
