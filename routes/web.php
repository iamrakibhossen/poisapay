<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\EmailVerificationController;
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
use App\Http\Controllers\Marketing\RatesController;
use App\Http\Controllers\PageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home')->name('home');

// Public CMS content (no auth).
Route::get('/faqs', FaqController::class)->name('faqs.public');
Route::get('/p/{slug}', [PageController::class, 'show'])->name('page.show');

// Public live crypto→BDT reference rates for the marketing converter (display only).
Route::get('/rates', RatesController::class)->name('marketing.rates');

// Public merchant marketing page (the console itself lives behind auth at /merchant).
Route::view('/merchants', 'marketing.merchants')->name('merchants');

// Guest auth (traditional controllers + Blade)
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

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

// Locale switch (persists to session + the authenticated user's preference).
Route::post('/locale', function (Request $request) {
    $locale = in_array($request->input('locale'), ['en', 'bn'], true) ? $request->input('locale') : 'en';
    session(['locale' => $locale]);

    if ($request->user()) {
        $request->user()->forceFill(['locale' => $locale])->save();
    }

    return back();
})->name('locale.switch');

// Email verification (Laravel standard route names, gated by settings elsewhere).
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', EmailVerificationPromptController::class)->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', EmailVerificationController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    })->middleware('throttle:6,1')->name('verification.send');
});

// End an operator impersonation session (the impersonated user is on the web guard).
Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])
    ->middleware('auth')->name('impersonate.stop');

// Authenticated app
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/live', [DashboardController::class, 'live'])->name('dashboard.live');
    Route::get('/wallet/{asset}', [AssetShowController::class, 'index'])->name('wallet.show');
    Route::get('/deposit', [DepositController::class, 'index'])->name('deposit');
    Route::get('/deposit/history', [DepositController::class, 'history'])->name('deposits');
    Route::get('/withdraw', [WithdrawController::class, 'index'])->name('withdraw');
    Route::get('/withdraw/history', [WithdrawController::class, 'history'])->name('withdrawals');
    Route::get('/send', [SendController::class, 'index'])->name('send');
    Route::get('/send/history', [SendController::class, 'history'])->name('transfers');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet');
    Route::get('/rewards', [RewardsController::class, 'index'])->name('rewards');

    // Frontend mutations — traditional form POST → redirect back with flash (no JSON API).
    Route::post('/wallet/favorite/{asset}', [WalletController::class, 'toggleFavorite'])->name('wallet.favorite');

    Route::post('/send', [SendController::class, 'send'])->name('send.execute');

    Route::post('/deposit', [DepositController::class, 'submit'])->name('deposit.submit');

    Route::post('/withdraw', [WithdrawController::class, 'submit'])->name('withdraw.submit');
    Route::post('/withdraw/cash', [WithdrawController::class, 'submitFiat'])->name('withdraw.fiat');
    Route::delete('/withdraw/accounts/{id}', [WithdrawController::class, 'deleteAccount'])->name('withdraw.account.delete');

    Route::post('/exchange/quote', [ExchangeController::class, 'quote'])->name('exchange.quote');
    Route::post('/exchange/confirm', [ExchangeController::class, 'confirm'])->name('exchange.confirm');

    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::put('/notifications/preferences', [NotificationController::class, 'savePreferences'])->name('notifications.preferences.update');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::post('/verification', [KycController::class, 'submit'])->name('kyc.submit');

    Route::put('/settings/profile', [SettingsController::class, 'saveProfile'])->name('settings.profile');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/2fa/enable', [SettingsController::class, 'enableTwoFactor'])->name('settings.2fa.enable');
    Route::post('/settings/2fa/confirm', [SettingsController::class, 'confirmTwoFactor'])->name('settings.2fa.confirm');
    Route::post('/settings/2fa/disable', [SettingsController::class, 'disableTwoFactor'])->name('settings.2fa.disable');
    Route::post('/settings/phone/otp', [SettingsController::class, 'sendPhoneOtp'])->name('settings.phone.otp');
    Route::post('/settings/phone/verify', [SettingsController::class, 'verifyPhone'])->name('settings.phone.verify');
    Route::delete('/settings/devices/{id}', [SettingsController::class, 'revokeDevice'])->name('settings.device.revoke');

    // Security centre (Wave 4): address whitelist, activity, anti-phishing, sessions.
    // The page now lives under Settings; keep the /security URL + name as a redirect
    // for bookmarks and existing links.
    Route::get('/security', fn () => redirect()->route('settings', ['tab' => 'security']))->name('security');
    Route::post('/security/addresses', [SecurityController::class, 'addAddress'])->name('security.address.add');
    Route::delete('/security/addresses/{id}', [SecurityController::class, 'deleteAddress'])->name('security.address.delete');
    Route::put('/security/anti-phishing', [SecurityController::class, 'saveAntiPhishing'])->name('security.anti-phishing');
    Route::post('/security/events/{id}/ack', [SecurityController::class, 'acknowledgeEvent'])->name('security.event.ack');
    Route::post('/security/sessions/logout-others', [SecurityController::class, 'logoutOtherSessions'])->name('security.sessions.logout-others');

    // Support centre (Wave 6).
    Route::get('/support', [SupportController::class, 'index'])->name('support');
    Route::get('/support/new', [SupportController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportController::class, 'store'])->name('support.store');
    Route::get('/support/{id}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{id}/reply', [SupportController::class, 'reply'])->name('support.reply');

    Route::get('/exchange', [ExchangeController::class, 'index'])->name('exchange');
    Route::get('/exchange/history', [ExchangeController::class, 'history'])->name('swaps');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::get('/verification', [KycController::class, 'index'])->name('kyc');
    Route::get('/settings/{tab?}', [SettingsController::class, 'index'])->name('settings')
        ->where('tab', 'profile|security|password|verification|devices|preferences|sessions');

    // Cards and Merchant page groups live in their own files (both page + app-api routes).
    require __DIR__.'/frontend/cards.php';
    require __DIR__.'/frontend/merchant.php';
    require __DIR__.'/frontend/p2p.php';
});

// Operator console lives in its own route file (DollarHub-style separation).
require __DIR__.'/admin.php';
