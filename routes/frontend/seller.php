<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\DomainController;
use App\Http\Controllers\Frontend\SellerController;
use App\Shop\Http\Controllers\PageBuilderController;
use Illuminate\Support\Facades\Route;

/*
 * Creator/seller platform (funnel platform) — page group. Included from web.php
 * inside the authenticated group. Traditional server-rendered pages + form-POST
 * mutations that redirect back. Frontend-first: onboarding UX only for now.
 */
Route::controller(SellerController::class)->group(function () {
    Route::get('/shop', 'index')->name('shop');
    Route::post('/shop/logo', 'uploadLogo')->name('shop.logo');
    Route::delete('/shop/logo', 'deleteLogo')->name('shop.logo.delete');
    Route::get('/shop/apply', 'apply')->name('shop.apply');
    Route::post('/shop/apply', 'submitApplication')->name('shop.apply.submit');
    Route::get('/shop/sales-pages', 'salesPages')->name('shop.sales-pages');
    Route::post('/shop/sales-pages', 'storeSalesPage')->name('shop.sales-pages.store');
    Route::delete('/shop/sales-pages/{slug}', 'deleteSalesPage')->name('shop.sales-page.delete');
    // Visual block-tree builder (rich Alpine editor + JSON endpoints).
    Route::get('/shop/sales-pages/{slug}/edit', [PageBuilderController::class, 'edit'])->name('shop.sales-page.edit');
    Route::patch('/shop/sales-pages/{slug}/document', [PageBuilderController::class, 'save'])->name('shop.sales-page.document');
    Route::post('/shop/sales-pages/{slug}/preview', [PageBuilderController::class, 'preview'])->name('shop.sales-page.preview');
    Route::post('/shop/sales-pages/{slug}/settings', [PageBuilderController::class, 'settings'])->name('shop.sales-page.update');
    Route::post('/shop/sales-pages/{slug}/publish', [PageBuilderController::class, 'publish'])->name('shop.sales-page.publish');
    Route::post('/shop/sales-pages/{slug}/duplicate', [PageBuilderController::class, 'duplicate'])->name('shop.sales-page.duplicate');
    Route::post('/shop/sales-pages/{slug}/template', [PageBuilderController::class, 'applyTemplate'])->name('shop.sales-page.template');
    Route::get('/shop/templates/{template}/preview', [PageBuilderController::class, 'templatePreview'])->name('shop.template.preview');
    Route::post('/shop/sales-pages/{slug}/revisions/{revision}/restore', [PageBuilderController::class, 'restore'])->name('shop.sales-page.restore');
    Route::get('/shop/funnels', 'funnels')->name('shop.funnels');
    Route::get('/shop/products', 'products')->name('shop.products');
    Route::get('/shop/products/create', 'createProduct')->name('shop.products.create');
    Route::post('/shop/products', 'storeProduct')->name('shop.products.store');
    Route::get('/shop/products/{id}/edit', 'editProduct')->name('shop.products.edit');
    Route::put('/shop/products/{id}', 'updateProduct')->name('shop.products.update');
    Route::delete('/shop/products/{id}', 'deleteProduct')->name('shop.products.delete');
    Route::post('/shop/products/{id}/publish', 'publishProduct')->name('shop.products.publish');
    Route::get('/shop/inbox', 'inbox')->name('shop.inbox');
    Route::get('/shop/orders', 'orders')->name('shop.orders');
    Route::get('/shop/orders/{id}', 'order')->name('shop.order');
    Route::post('/shop/orders/{id}/status', 'fulfilOrder')->name('shop.order.status');
    Route::post('/shop/orders/{id}/refund', 'refundOrder')->name('shop.order.refund');
    Route::post('/shop/orders/{id}/refund-requests/{refundRequest}/approve', 'approveRefundRequest')->name('shop.order.refund-request.approve');
    Route::post('/shop/orders/{id}/refund-requests/{refundRequest}/reject', 'rejectRefundRequest')->name('shop.order.refund-request.reject');
    Route::post('/shop/orders/{id}/messages', 'sendOrderMessage')->name('shop.order.message');
    Route::get('/shop/reviews', 'reviews')->name('shop.reviews');
    Route::post('/shop/reviews/{id}/reply', 'replyReview')->name('shop.reviews.reply');
    Route::get('/shop/customers', 'customers')->name('shop.customers');
    Route::get('/shop/coupons', 'coupons')->name('shop.coupons');
    Route::post('/shop/coupons', 'storeCoupon')->name('shop.coupons.store');
    Route::post('/shop/coupons/{id}/toggle', 'toggleCoupon')->name('shop.coupons.toggle');
    Route::delete('/shop/coupons/{id}', 'destroyCoupon')->name('shop.coupons.destroy');
    Route::get('/shop/analytics', 'analytics')->name('shop.analytics');
    Route::get('/shop/earnings', 'earnings')->name('shop.earnings');
});

// Custom domains — own controller (real connect/verify/remove flow, one per page).
Route::controller(DomainController::class)->group(function () {
    Route::get('/shop/domains', 'index')->name('shop.domains');
    Route::post('/shop/domains', 'store')->name('shop.domains.store');
    Route::post('/shop/domains/{domain}/verify', 'verify')->name('shop.domains.verify');
    Route::delete('/shop/domains/{domain}', 'destroy')->name('shop.domains.destroy');
});
