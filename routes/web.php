<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\PageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.home')->name('home');

// Public CMS content (no auth).
Route::get('/faqs', FaqController::class)->name('faqs.public');
Route::get('/p/{slug}', [PageController::class, 'show'])->name('page.show');

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
Route::post('/impersonate/stop', [App\Http\Controllers\ImpersonationController::class, 'stop'])
    ->middleware('auth')->name('impersonate.stop');

// Authenticated app
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Frontend\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/wallet/{asset}', [App\Http\Controllers\Frontend\AssetShowController::class, 'index'])->name('wallet.show');
    Route::get('/deposit', [App\Http\Controllers\Frontend\DepositController::class, 'index'])->name('deposit');
    Route::get('/deposit/history', [App\Http\Controllers\Frontend\DepositController::class, 'history'])->name('deposits');
    Route::get('/withdraw', [App\Http\Controllers\Frontend\WithdrawController::class, 'index'])->name('withdraw');
    Route::get('/withdraw/history', [App\Http\Controllers\Frontend\WithdrawController::class, 'history'])->name('withdrawals');
    Route::get('/send', [App\Http\Controllers\Frontend\SendController::class, 'index'])->name('send');
    Route::get('/send/history', [App\Http\Controllers\Frontend\SendController::class, 'history'])->name('transfers');
    Route::get('/transactions', [App\Http\Controllers\Frontend\TransactionController::class, 'index'])->name('transactions');
    Route::get('/wallet', [App\Http\Controllers\Frontend\WalletController::class, 'index'])->name('wallet');
    Route::get('/rewards', [App\Http\Controllers\Frontend\RewardsController::class, 'index'])->name('rewards');

    // Frontend mutations — traditional form POST → redirect back with flash (no JSON API).
    Route::post('/wallet/favorite/{asset}', [App\Http\Controllers\Frontend\WalletController::class, 'toggleFavorite'])->name('wallet.favorite');

    Route::post('/send', [App\Http\Controllers\Frontend\SendController::class, 'send'])->name('send.execute');

    Route::post('/deposit', [App\Http\Controllers\Frontend\DepositController::class, 'submit'])->name('deposit.submit');

    Route::post('/withdraw', [App\Http\Controllers\Frontend\WithdrawController::class, 'submit'])->name('withdraw.submit');
    Route::post('/withdraw/cash', [App\Http\Controllers\Frontend\WithdrawController::class, 'submitFiat'])->name('withdraw.fiat');
    Route::delete('/withdraw/accounts/{id}', [App\Http\Controllers\Frontend\WithdrawController::class, 'deleteAccount'])->name('withdraw.account.delete');

    Route::post('/exchange/quote', [App\Http\Controllers\Frontend\ExchangeController::class, 'quote'])->name('exchange.quote');
    Route::post('/exchange/confirm', [App\Http\Controllers\Frontend\ExchangeController::class, 'confirm'])->name('exchange.confirm');

    Route::post('/notifications/{id}/read', [App\Http\Controllers\Frontend\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [App\Http\Controllers\Frontend\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::put('/notifications/preferences', [App\Http\Controllers\Frontend\NotificationController::class, 'savePreferences'])->name('notifications.preferences');

    Route::post('/verification', [App\Http\Controllers\Frontend\KycController::class, 'submit'])->name('kyc.submit');

    Route::post('/credit/open', [App\Http\Controllers\Frontend\CreditController::class, 'openLine'])->name('credit.open');
    Route::post('/credit/draw', [App\Http\Controllers\Frontend\CreditController::class, 'draw'])->name('credit.draw');
    Route::post('/credit/repay', [App\Http\Controllers\Frontend\CreditController::class, 'repay'])->name('credit.repay');

    Route::put('/settings/profile', [App\Http\Controllers\Frontend\SettingsController::class, 'saveProfile'])->name('settings.profile');
    Route::post('/settings/2fa/enable', [App\Http\Controllers\Frontend\SettingsController::class, 'enableTwoFactor'])->name('settings.2fa.enable');
    Route::post('/settings/2fa/confirm', [App\Http\Controllers\Frontend\SettingsController::class, 'confirmTwoFactor'])->name('settings.2fa.confirm');
    Route::post('/settings/2fa/disable', [App\Http\Controllers\Frontend\SettingsController::class, 'disableTwoFactor'])->name('settings.2fa.disable');
    Route::post('/settings/phone/otp', [App\Http\Controllers\Frontend\SettingsController::class, 'sendPhoneOtp'])->name('settings.phone.otp');
    Route::post('/settings/phone/verify', [App\Http\Controllers\Frontend\SettingsController::class, 'verifyPhone'])->name('settings.phone.verify');
    Route::delete('/settings/devices/{id}', [App\Http\Controllers\Frontend\SettingsController::class, 'revokeDevice'])->name('settings.device.revoke');

    Route::get('/exchange', [App\Http\Controllers\Frontend\ExchangeController::class, 'index'])->name('exchange');
    Route::get('/exchange/history', [App\Http\Controllers\Frontend\ExchangeController::class, 'history'])->name('swaps');
    Route::get('/credit', [App\Http\Controllers\Frontend\CreditController::class, 'index'])->name('credit');
    Route::get('/notifications', [App\Http\Controllers\Frontend\NotificationController::class, 'index'])->name('notifications');
    Route::get('/verification', [App\Http\Controllers\Frontend\KycController::class, 'index'])->name('kyc');
    Route::get('/settings/{tab?}', [App\Http\Controllers\Frontend\SettingsController::class, 'index'])->name('settings')
        ->where('tab', 'profile|security|verification|devices|preferences|sessions');

    // Cards and Merchant page groups live in their own files (both page + app-api routes).
    require __DIR__.'/frontend/cards.php';
    require __DIR__.'/frontend/merchant.php';
});

// Operator console lives in its own route file (DollarHub-style separation).
require __DIR__.'/admin.php';
