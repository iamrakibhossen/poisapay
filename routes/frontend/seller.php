<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\SellerController;
use Illuminate\Support\Facades\Route;

/*
 * Creator/seller platform (funnel platform) — page group. Included from web.php
 * inside the authenticated group. Traditional server-rendered pages + form-POST
 * mutations that redirect back. Frontend-first: onboarding UX only for now.
 */
Route::controller(SellerController::class)->group(function () {
    Route::get('/seller', 'index')->name('seller');
    Route::get('/seller/apply', 'apply')->name('seller.apply');
    Route::post('/seller/apply', 'submitApplication')->name('seller.apply.submit');
    Route::get('/seller/sales-pages', 'salesPages')->name('seller.sales-pages');
    Route::get('/seller/sales-pages/{slug}/edit', 'editSalesPage')->name('seller.sales-page.edit');
    Route::get('/seller/funnels', 'funnels')->name('seller.funnels');
    Route::get('/seller/products', 'products')->name('seller.products');
    Route::get('/seller/products/create', 'createProduct')->name('seller.products.create');
    Route::post('/seller/products', 'storeProduct')->name('seller.products.store');
    Route::get('/seller/inbox', 'inbox')->name('seller.inbox');
    Route::get('/seller/orders', 'orders')->name('seller.orders');
    Route::get('/seller/orders/{id}', 'order')->name('seller.order');
    Route::get('/seller/reviews', 'reviews')->name('seller.reviews');
    Route::get('/seller/customers', 'customers')->name('seller.customers');
    Route::get('/seller/coupons', 'coupons')->name('seller.coupons');
    Route::get('/seller/analytics', 'analytics')->name('seller.analytics');
    Route::get('/seller/earnings', 'earnings')->name('seller.earnings');
    Route::get('/seller/domains', 'domains')->name('seller.domains');
});
