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
    Route::get('/sell', 'index')->name('sell');
    Route::get('/sell/apply', 'apply')->name('sell.apply');
    Route::post('/sell/apply', 'submitApplication')->name('sell.apply.submit');
    Route::get('/sell/sales-pages', 'salesPages')->name('sell.sales-pages');
    Route::post('/sell/sales-pages', 'storeSalesPage')->name('sell.sales-pages.store');
    Route::get('/sell/sales-pages/{slug}/edit', 'editSalesPage')->name('sell.sales-page.edit');
    Route::post('/sell/sales-pages/{slug}', 'saveSalesPage')->name('sell.sales-page.update');
    Route::post('/sell/sales-pages/{slug}/publish', 'publishSalesPage')->name('sell.sales-page.publish');
    Route::get('/sell/funnels', 'funnels')->name('sell.funnels');
    Route::get('/sell/products', 'products')->name('sell.products');
    Route::get('/sell/products/create', 'createProduct')->name('sell.products.create');
    Route::post('/sell/products', 'storeProduct')->name('sell.products.store');
    Route::get('/sell/products/{id}/edit', 'editProduct')->name('sell.products.edit');
    Route::put('/sell/products/{id}', 'updateProduct')->name('sell.products.update');
    Route::get('/sell/inbox', 'inbox')->name('sell.inbox');
    Route::get('/sell/orders', 'orders')->name('sell.orders');
    Route::get('/sell/orders/{id}', 'order')->name('sell.order');
    Route::post('/sell/orders/{id}/status', 'fulfilOrder')->name('sell.order.status');
    Route::get('/sell/reviews', 'reviews')->name('sell.reviews');
    Route::get('/sell/customers', 'customers')->name('sell.customers');
    Route::get('/sell/coupons', 'coupons')->name('sell.coupons');
    Route::get('/sell/analytics', 'analytics')->name('sell.analytics');
    Route::get('/sell/earnings', 'earnings')->name('sell.earnings');
    Route::get('/sell/domains', 'domains')->name('sell.domains');
});
