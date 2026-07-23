<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\P2pChatController;
use App\Http\Controllers\Frontend\P2pController;
use App\Http\Middleware\EnsureP2pEnabled;
use Illuminate\Support\Facades\Route;

/*
 * P2P marketplace (included from web.php inside the authenticated group). The
 * whole surface is hidden (404) unless the `p2p_enabled` flag is on.
 */
Route::middleware(EnsureP2pEnabled::class)->group(function () {
    Route::controller(P2pController::class)->group(function () {
        // Marketplace + ads.
        Route::get('/p2p', 'index')->name('p2p');
        Route::get('/p2p/ads', 'myAds')->name('p2p.ads');
        Route::get('/p2p/ads/create', 'createAd')->name('p2p.ads.create');
        Route::post('/p2p/ads', 'storeAd')->name('p2p.ads.store');
        Route::get('/p2p/ads/{ad}/edit', 'editAd')->name('p2p.ads.edit');
        Route::put('/p2p/ads/{ad}', 'updateAd')->name('p2p.ads.update');
        Route::post('/p2p/ads/{ad}/toggle', 'toggleAd')->name('p2p.ads.toggle');

        // Payment accounts (the seller's payout details shown to a buyer once an order is open).
        Route::get('/p2p/payment-methods', 'paymentMethods')->name('p2p.payment-methods');
        Route::post('/p2p/payment-methods', 'storePaymentMethod')->name('p2p.payment-methods.store');
        Route::delete('/p2p/payment-methods/{method}', 'destroyPaymentMethod')->name('p2p.payment-methods.destroy');

        // Merchant reputation + availability.
        Route::post('/p2p/merchant/online', 'toggleOnline')->name('p2p.merchant.online');
        Route::post('/p2p/merchant/vacation', 'toggleVacation')->name('p2p.merchant.vacation');
        Route::get('/p2p/merchant/{user}', 'merchant')->name('p2p.merchant');

        // Orders + lifecycle.
        Route::get('/p2p/orders', 'orders')->name('p2p.orders');
        Route::post('/p2p/orders', 'createOrder')->name('p2p.orders.store')->middleware('throttle:30,1');
        Route::get('/p2p/orders/{order}', 'order')->name('p2p.order');
        Route::post('/p2p/orders/{order}/paid', 'markPaid')->name('p2p.order.paid');
        Route::post('/p2p/orders/{order}/release', 'release')->name('p2p.order.release');
        Route::post('/p2p/orders/{order}/cancel', 'cancel')->name('p2p.order.cancel');
        Route::post('/p2p/orders/{order}/dispute', 'dispute')->name('p2p.order.dispute');
        Route::post('/p2p/orders/{order}/dispute/evidence', 'addEvidence')->name('p2p.dispute.evidence.add')->middleware('throttle:20,1');
        Route::get('/p2p/dispute-evidence/{evidence}', 'disputeEvidence')->name('p2p.dispute.evidence');
    });

    // Order chat (Phase 2) — durable path; live delivery is over the p2p.order.{id} channel.
    Route::controller(P2pChatController::class)->group(function () {
        Route::get('/p2p/orders/{order}/messages', 'index')->name('p2p.messages');
        Route::post('/p2p/orders/{order}/messages', 'store')->name('p2p.messages.send')->middleware('throttle:60,1');
        Route::post('/p2p/orders/{order}/messages/read', 'read')->name('p2p.messages.read');
        Route::get('/p2p/messages/{message}/attachment', 'attachment')->name('p2p.messages.attachment');
    });
});
