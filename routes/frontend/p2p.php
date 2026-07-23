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
    // Marketplace + ads.
    Route::get('/p2p', [P2pController::class, 'index'])->name('p2p');
    Route::get('/p2p/ads', [P2pController::class, 'myAds'])->name('p2p.ads');
    Route::get('/p2p/ads/create', [P2pController::class, 'createAd'])->name('p2p.ads.create');
    Route::post('/p2p/ads', [P2pController::class, 'storeAd'])->name('p2p.ads.store');
    Route::get('/p2p/ads/{ad}/edit', [P2pController::class, 'editAd'])->name('p2p.ads.edit');
    Route::put('/p2p/ads/{ad}', [P2pController::class, 'updateAd'])->name('p2p.ads.update');
    Route::post('/p2p/ads/{ad}/toggle', [P2pController::class, 'toggleAd'])->name('p2p.ads.toggle');

    // Payment accounts (the seller's payout details shown to a buyer once an order is open).
    Route::get('/p2p/payment-methods', [P2pController::class, 'paymentMethods'])->name('p2p.payment-methods');
    Route::post('/p2p/payment-methods', [P2pController::class, 'storePaymentMethod'])->name('p2p.payment-methods.store');
    Route::delete('/p2p/payment-methods/{method}', [P2pController::class, 'destroyPaymentMethod'])->name('p2p.payment-methods.destroy');

    // Merchant reputation + availability.
    Route::post('/p2p/merchant/online', [P2pController::class, 'toggleOnline'])->name('p2p.merchant.online');
    Route::post('/p2p/merchant/vacation', [P2pController::class, 'toggleVacation'])->name('p2p.merchant.vacation');
    Route::get('/p2p/merchant/{user}', [P2pController::class, 'merchant'])->name('p2p.merchant');

    // Orders + lifecycle.
    Route::get('/p2p/orders', [P2pController::class, 'orders'])->name('p2p.orders');
    Route::post('/p2p/orders', [P2pController::class, 'createOrder'])->name('p2p.orders.store')->middleware('throttle:30,1');
    Route::get('/p2p/orders/{order}', [P2pController::class, 'order'])->name('p2p.order');
    Route::post('/p2p/orders/{order}/paid', [P2pController::class, 'markPaid'])->name('p2p.order.paid');
    Route::post('/p2p/orders/{order}/release', [P2pController::class, 'release'])->name('p2p.order.release');
    Route::post('/p2p/orders/{order}/cancel', [P2pController::class, 'cancel'])->name('p2p.order.cancel');
    Route::post('/p2p/orders/{order}/dispute', [P2pController::class, 'dispute'])->name('p2p.order.dispute');
    Route::post('/p2p/orders/{order}/dispute/evidence', [P2pController::class, 'addEvidence'])->name('p2p.dispute.evidence.add')->middleware('throttle:20,1');
    Route::get('/p2p/dispute-evidence/{evidence}', [P2pController::class, 'disputeEvidence'])->name('p2p.dispute.evidence');

    // Order chat (Phase 2) — durable path; live delivery is over the p2p.order.{id} channel.
    Route::get('/p2p/orders/{order}/messages', [P2pChatController::class, 'index'])->name('p2p.messages');
    Route::post('/p2p/orders/{order}/messages', [P2pChatController::class, 'store'])
        ->name('p2p.messages.send')->middleware('throttle:60,1');
    Route::post('/p2p/orders/{order}/messages/read', [P2pChatController::class, 'read'])->name('p2p.messages.read');
    Route::get('/p2p/messages/{message}/attachment', [P2pChatController::class, 'attachment'])->name('p2p.messages.attachment');
});
