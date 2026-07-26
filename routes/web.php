<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Frontend\AssetShowController;
use App\Http\Controllers\Frontend\DashboardController;
use App\Http\Controllers\Frontend\DepositController;
use App\Http\Controllers\Frontend\ExchangeController;
use App\Http\Controllers\Frontend\KycController;
use App\Http\Controllers\Frontend\NotificationController;
use App\Http\Controllers\Frontend\PurchasesController;
use App\Http\Controllers\Frontend\RewardsController;
use App\Http\Controllers\Frontend\SecurityController;
use App\Http\Controllers\Frontend\SendController;
use App\Http\Controllers\Frontend\SettingsController;
use App\Http\Controllers\Frontend\SupportController;
use App\Http\Controllers\Frontend\TransactionController;
use App\Http\Controllers\Frontend\WalletController;
use App\Http\Controllers\Frontend\WithdrawController;
use App\Http\Controllers\Funnel\PublicSalesController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (guest-accessible) routes
|--------------------------------------------------------------------------
*/
// Public marketing / landing pages (home `/`, /help-center, /pages/{slug}, /prices,
// /rates, /status, /products/{product}, /merchants) are owned by the isolated Landing
// module — see routes/landing.php. Route names are unchanged, so every route() resolves.

// Funnel platform — public product sales pages (standalone, conversion-first).
Route::controller(PublicSalesController::class)->group(function () {
    Route::get('/p/{slug}', 'show')->name('funnel.sales');
    // "Buy" hands off to /buy (account gate → checkout). The checkout page itself
    // lives at /checkout. Route names are kept stable so every route() resolves.
    Route::post('/p/{slug}/buy', 'checkout')->name('funnel.checkout');
    Route::get('/p/{slug}/checkout', 'pay')->name('funnel.pay');
    Route::post('/p/{slug}/checkout', 'payConfirm')->name('funnel.pay.confirm');
    Route::get('/p/{slug}/account', 'account')->name('funnel.account');
    Route::post('/p/{slug}/account', 'accountSubmit')->middleware('throttle:10,1')->name('funnel.account.submit');
    Route::get('/p/{slug}/thank-you', 'thankYou')->name('funnel.thankyou');
    Route::post('/p/{slug}/upsell', 'upsellAccept')->name('funnel.upsell');

    // Central hosted checkout (always on the platform host). A storefront's Buy form
    // posts to /checkout (cross-origin from a custom domain); the checkout PAGE itself
    // lives at /checkout/{product} so payment runs on one trusted, product-keyed URL.
    Route::post('/checkout', 'enter')->name('checkout.enter');           // CSRF-exempt (see bootstrap/app.php)
    Route::get('/checkout/{product}', 'directCheckout')->name('checkout.show');
    Route::post('/checkout/{product}', 'confirmDirect')->name('checkout.pay');
    Route::get('/checkout/{product}/thank-you', 'centralThankYou')->name('checkout.thankyou');
});
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Guest auth (traditional controllers + Blade)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.attempt');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Authenticated app
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

    // Email verification (Laravel standard route names, gated by settings elsewhere).
    Route::get('/email/verify', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', EmailVerificationController::class)
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')->name('verification.send');

    // ── Pages (server-rendered) ──
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/live', [DashboardController::class, 'live'])->name('dashboard.live');
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet');
    Route::get('/wallet/{asset}', [AssetShowController::class, 'index'])->name('wallet.show');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
    Route::get('/rewards', [RewardsController::class, 'index'])->name('rewards');

    // ── Wallet mutations ──
    Route::post('/wallet/favorite/{asset}', [WalletController::class, 'toggleFavorite'])->name('wallet.favorite');

    // ── Deposit ──
    Route::controller(DepositController::class)->prefix('deposit')->name('deposit.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/history', 'history')->name('history');
        Route::post('/', 'submit')->name('submit');
    });

    // ── Withdraw ──
    Route::controller(WithdrawController::class)->prefix('withdraw')->name('withdraw.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/history', 'history')->name('history');
        Route::post('/', 'submit')->name('submit');
        Route::post('/cash', 'submitFiat')->name('fiat');
        Route::delete('/accounts/{id}', 'deleteAccount')->name('account.delete');
    });

    // ── Send ──
    Route::controller(SendController::class)->prefix('send')->name('send.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/history', 'history')->name('history');
        Route::post('/', 'send')->name('execute');
    });

    // ── Exchange ──
    Route::controller(ExchangeController::class)->prefix('exchange')->name('exchange.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/history', 'history')->name('history');
        Route::post('/quote', 'quote')->name('quote');
        Route::post('/confirm', 'confirm')->name('confirm');
    });

    // ── Notifications ──
    Route::controller(NotificationController::class)->prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/preferences', 'preferences')->name('preferences');
        Route::put('/preferences', 'savePreferences')->name('preferences.update');
        Route::post('/{id}/read', 'markRead')->name('read');
        Route::post('/read-all', 'markAllRead')->name('read-all');
    });

    // ── KYC / verification ──
    Route::controller(KycController::class)->prefix('verification')->name('kyc.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'submit')->name('submit');
    });

    // ── Settings ──
    Route::controller(SettingsController::class)->prefix('settings')->name('settings.')->group(function () {
        Route::put('/profile', 'saveProfile')->name('profile');
        Route::put('/preferences', 'saveSpendingPriority')->name('preferences');
        Route::put('/password', 'updatePassword')->name('password');
        // 2FA/OTP endpoints are brute-force targets — throttle code checks; OTP sends are stricter.
        Route::post('/2fa/enable', 'enableTwoFactor')->middleware('throttle:10,1')->name('2fa.enable');
        Route::post('/2fa/confirm', 'confirmTwoFactor')->middleware('throttle:10,1')->name('2fa.confirm');
        Route::post('/2fa/disable', 'disableTwoFactor')->middleware('throttle:10,1')->name('2fa.disable');
        Route::post('/phone/otp', 'sendPhoneOtp')->middleware('throttle:6,1')->name('phone.otp');
        Route::post('/phone/verify', 'verifyPhone')->middleware('throttle:10,1')->name('phone.verify');
        Route::delete('/devices/{id}', 'revokeDevice')->name('device.revoke');
        Route::get('/{tab?}', 'index')->name('index')
            ->where('tab', 'profile|security|password|verification|devices|preferences|sessions');
    });

    // ── Security centre (Wave 4). The page now lives under Settings; /security
    //    stays as a redirect for bookmarks and existing links. ──
    Route::controller(SecurityController::class)->prefix('security')->name('security.')->group(function () {
        Route::get('/', 'redirectToSettings')->name('index');
        Route::post('/addresses', 'addAddress')->name('address.add');
        Route::delete('/addresses/{id}', 'deleteAddress')->name('address.delete');
        Route::put('/anti-phishing', 'saveAntiPhishing')->name('anti-phishing');
        Route::post('/events/{id}/ack', 'acknowledgeEvent')->name('event.ack');
        Route::post('/sessions/logout-others', 'logoutOtherSessions')->name('sessions.logout-others');
    });

    // ── Support centre (Wave 6) ──
    Route::controller(SupportController::class)->prefix('support')->name('support.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/new', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{id}', 'show')->name('show');
        Route::post('/{id}/reply', 'reply')->name('reply');
    });

    // Cards, Merchant and P2P page groups live in their own module files
    // (both page + mutation routes); already inside this auth group.
    require __DIR__.'/frontend/cards.php';
    require __DIR__.'/frontend/merchant.php';
    require __DIR__.'/frontend/p2p.php';
    require __DIR__.'/frontend/seller.php';

    // Customer portal — purchases, downloads, courses, license keys.
    Route::get('/purchases', [PurchasesController::class, 'index'])->name('purchases');
    Route::get('/purchases/order/{order}', [PurchasesController::class, 'show'])->name('purchases.show');
    Route::get('/purchases/{item}/download', [PurchasesController::class, 'download'])->name('purchases.download');
    Route::get('/purchases/{order}/messages', [PurchasesController::class, 'messages'])->name('purchases.messages');
    Route::post('/purchases/{order}/messages', [PurchasesController::class, 'sendMessage'])->name('purchases.messages.send');
    Route::post('/purchases/{order}/review', [PurchasesController::class, 'submitReview'])->name('purchases.review');
    Route::post('/purchases/{order}/refund', [PurchasesController::class, 'requestRefund'])->name('purchases.refund');
    Route::post('/purchases/refund/{refundRequest}/cancel', [PurchasesController::class, 'cancelRefund'])->name('purchases.refund.cancel');
    Route::post('/purchases/refund/{refundRequest}/escalate', [PurchasesController::class, 'escalateRefund'])->name('purchases.refund.escalate');
});

// Operator console lives in its own route file (DollarHub-style separation).
require __DIR__.'/admin.php';
