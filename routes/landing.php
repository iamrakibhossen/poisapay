<?php

declare(strict_types=1);

use App\Http\Controllers\Landing\FaqController;
use App\Http\Controllers\Landing\HomeController;
use App\Http\Controllers\Landing\PageController;
use App\Http\Controllers\Landing\PricesController;
use App\Http\Controllers\Landing\ProductController;
use App\Http\Controllers\Landing\RatesController;
use App\Http\Controllers\Landing\StatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landing routes (isolated module)
|--------------------------------------------------------------------------
|
| Registered by App\Providers\LandingServiceProvider, separate from web.php.
| These own the public marketing/landing surface. Route NAMES are kept stable
| (home, help-center, page.show, marketing.prices, marketing.rates, status,
| products.show, merchants) — the app's auth/admin/footer/topbar link to them —
| so relocating them here from web.php is transparent to every other module.
| Wrapped in the `web` middleware group (session, CSRF, SetLocale) exactly as
| when they lived in web.php.
|
*/

Route::middleware('web')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    // Merchant Pay marketing lives as a product page; keep /merchants as a named redirect.
    Route::get('/merchants', fn () => redirect()->route('products.show', 'merchant-pay'))->name('merchants');

    Route::get('/help-center', FaqController::class)->name('help-center');
    Route::redirect('/faqs', '/help-center'); // legacy path

    Route::get('/prices', PricesController::class)->name('marketing.prices');   // live crypto→BDT prices page (HTML)
    Route::get('/rates', RatesController::class)->name('marketing.rates');      // live reference rates (JSON feed)
    Route::get('/status', StatusController::class)->name('status');             // live system status (footer)
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/pages/{slug}', [PageController::class, 'show'])->name('page.show');
});
