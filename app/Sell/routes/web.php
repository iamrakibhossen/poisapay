<?php

declare(strict_types=1);

use App\Sell\Http\Controllers\Admin\SellerReviewController;
use App\Sell\Http\Controllers\CheckoutController;
use App\Sell\Http\Controllers\ProductController;
use App\Sell\Http\Controllers\PublicPageController;
use App\Sell\Http\Controllers\SalesPageController;
use App\Sell\Http\Controllers\SellerApplicationController;
use Illuminate\Support\Facades\Route;

/*
 * Sell module routes (loaded by SellServiceProvider). REST, versioned-ready.
 * Seller-facing endpoints use the web `auth` guard; operator endpoints use the
 * separate `admin` guard — mirroring the platform's guard split.
 */

Route::middleware(['web', 'auth'])->prefix('api/sell')->name('sell.')->group(function () {
    Route::post('/apply', [SellerApplicationController::class, 'store'])->name('apply');

    // Checkout (buyer-facing — any authenticated user)
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout');

    // Catalog
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}/publish', [ProductController::class, 'publish'])->name('products.publish');
    Route::post('/products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');

    // Sales pages
    Route::apiResource('sales-pages', SalesPageController::class)->parameters(['sales-pages' => 'salesPage']);
    Route::post('/sales-pages/{salesPage}/publish', [SalesPageController::class, 'publish'])->name('sales-pages.publish');
    Route::post('/sales-pages/{salesPage}/archive', [SalesPageController::class, 'archive'])->name('sales-pages.archive');
});

// Public sales page — cache-first, no auth.
Route::middleware('web')->get('/api/sell/public/pages/{slug}', [PublicPageController::class, 'show'])->name('sell.public.page');

Route::middleware(['web', 'auth:admin'])->prefix('api/admin/sell/sellers')->name('sell.admin.sellers.')->group(function () {
    Route::post('/{seller}/approve', [SellerReviewController::class, 'approve'])->name('approve');
    Route::post('/{seller}/reject', [SellerReviewController::class, 'reject'])->name('reject');
    Route::post('/{seller}/suspend', [SellerReviewController::class, 'suspend'])->name('suspend');
});
