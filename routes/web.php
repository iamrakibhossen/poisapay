<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\Frontend\AssetShowController;
use App\Http\Controllers\Frontend\DashboardController;
use App\Http\Controllers\Frontend\DepositController;
use App\Http\Controllers\Frontend\ExchangeController;
use App\Http\Controllers\Frontend\KycController;
use App\Http\Controllers\Frontend\NotificationController;
use App\Http\Controllers\Frontend\RewardsController;
use App\Http\Controllers\Frontend\SecurityController;
use App\Http\Controllers\Frontend\SendController;
use App\Http\Controllers\Frontend\SettingsController;
use App\Http\Controllers\Frontend\SupportController;
use App\Http\Controllers\Frontend\TransactionController;
use App\Http\Controllers\Frontend\WalletController;
use App\Http\Controllers\Frontend\WithdrawController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Marketing\RatesController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (guest-accessible) routes
|--------------------------------------------------------------------------
*/
Route::view('/', 'marketing.home')->name('home');
Route::view('/merchants', 'marketing.merchants')->name('merchants');   // marketing (console lives at /merchant, behind auth)
Route::get('/faqs', FaqController::class)->name('faqs.public');
Route::get('/p/{slug}', [PageController::class, 'show'])->name('page.show');
Route::get('/rates', RatesController::class)->name('marketing.rates');  // live crypto→BDT reference rates (display only)
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
        Route::put('/password', 'updatePassword')->name('password');
        Route::post('/2fa/enable', 'enableTwoFactor')->name('2fa.enable');
        Route::post('/2fa/confirm', 'confirmTwoFactor')->name('2fa.confirm');
        Route::post('/2fa/disable', 'disableTwoFactor')->name('2fa.disable');
        Route::post('/phone/otp', 'sendPhoneOtp')->name('phone.otp');
        Route::post('/phone/verify', 'verifyPhone')->name('phone.verify');
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
});

// Operator console lives in its own route file (DollarHub-style separation).
require __DIR__.'/admin.php';
