<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\SellerController;
use App\Sell\Http\Controllers\PageBuilderController;
use Illuminate\Support\Facades\Route;

/*
 * Creator/seller platform (funnel platform) — page group. Included from web.php
 * inside the authenticated group. Traditional server-rendered pages + form-POST
 * mutations that redirect back. Frontend-first: onboarding UX only for now.
 */
Route::controller(SellerController::class)->group(function () {
    Route::get('/sell', 'index')->name('sell');
    Route::post('/sell/logo', 'uploadLogo')->name('sell.logo');
    Route::delete('/sell/logo', 'deleteLogo')->name('sell.logo.delete');
    Route::get('/sell/apply', 'apply')->name('sell.apply');
    Route::post('/sell/apply', 'submitApplication')->name('sell.apply.submit');
    Route::get('/sell/sales-pages', 'salesPages')->name('sell.sales-pages');
    Route::post('/sell/sales-pages', 'storeSalesPage')->name('sell.sales-pages.store');
    // Visual block-tree builder (rich Alpine editor + JSON endpoints).
    Route::get('/sell/sales-pages/{slug}/edit', [PageBuilderController::class, 'edit'])->name('sell.sales-page.edit');
    Route::patch('/sell/sales-pages/{slug}/document', [PageBuilderController::class, 'save'])->name('sell.sales-page.document');
    Route::post('/sell/sales-pages/{slug}/preview', [PageBuilderController::class, 'preview'])->name('sell.sales-page.preview');
    Route::post('/sell/sales-pages/{slug}/settings', [PageBuilderController::class, 'settings'])->name('sell.sales-page.update');
    Route::post('/sell/sales-pages/{slug}/publish', [PageBuilderController::class, 'publish'])->name('sell.sales-page.publish');
    Route::post('/sell/sales-pages/{slug}/duplicate', [PageBuilderController::class, 'duplicate'])->name('sell.sales-page.duplicate');
    Route::post('/sell/sales-pages/{slug}/revisions/{revision}/restore', [PageBuilderController::class, 'restore'])->name('sell.sales-page.restore');
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
    Route::post('/sell/orders/{id}/refund', 'refundOrder')->name('sell.order.refund');
    Route::post('/sell/orders/{id}/refund-requests/{refundRequest}/approve', 'approveRefundRequest')->name('sell.order.refund-request.approve');
    Route::post('/sell/orders/{id}/refund-requests/{refundRequest}/reject', 'rejectRefundRequest')->name('sell.order.refund-request.reject');
    Route::post('/sell/orders/{id}/messages', 'sendOrderMessage')->name('sell.order.message');
    Route::get('/sell/reviews', 'reviews')->name('sell.reviews');
    Route::post('/sell/reviews/{id}/reply', 'replyReview')->name('sell.reviews.reply');
    Route::get('/sell/customers', 'customers')->name('sell.customers');
    Route::get('/sell/coupons', 'coupons')->name('sell.coupons');
    Route::post('/sell/coupons', 'storeCoupon')->name('sell.coupons.store');
    Route::post('/sell/coupons/{id}/toggle', 'toggleCoupon')->name('sell.coupons.toggle');
    Route::delete('/sell/coupons/{id}', 'destroyCoupon')->name('sell.coupons.destroy');
    Route::get('/sell/analytics', 'analytics')->name('sell.analytics');
    Route::get('/sell/earnings', 'earnings')->name('sell.earnings');
    Route::get('/sell/domains', 'domains')->name('sell.domains');
});
