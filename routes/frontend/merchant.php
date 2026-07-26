<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\MerchantController;
use App\Http\Controllers\Frontend\PayInvoiceController;
use Illuminate\Support\Facades\Route;

/*
 * Merchant console + invoice payment page group. Included from web.php inside the
 * authenticated group (auth middleware applied to all). Traditional server-rendered
 * pages + form-POST mutations that redirect back.
 */

// Merchant Pay console (page + mutations). URL is /merchant-pay; route NAMES stay
// `merchant*` so existing route('merchant') references keep resolving.
Route::controller(MerchantController::class)->group(function () {
    Route::get('/merchant-pay', 'index')->name('merchant');
    Route::post('/merchant-pay/register', 'register')->name('merchant.register');
    Route::put('/merchant-pay/profile', 'saveProfile')->name('merchant.profile');
    Route::post('/merchant-pay/invoices', 'createInvoice')->name('merchant.invoice.create');
    Route::post('/merchant-pay/invoices/{id}/cancel', 'cancelInvoice')->name('merchant.invoice.cancel');
    Route::post('/merchant-pay/invoices/{id}/refund', 'refundInvoice')->name('merchant.invoice.refund');
});

// Invoice payment (public-facing pay page + execution).
Route::controller(PayInvoiceController::class)->group(function () {
    Route::get('/pay/{invoice}', 'index')->name('pay.invoice');
    Route::post('/pay/{invoice}', 'pay')->name('pay.execute');
});
