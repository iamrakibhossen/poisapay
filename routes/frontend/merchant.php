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

// Pages (server-rendered).
Route::get('/merchant', [MerchantController::class, 'index'])->name('merchant');
Route::get('/pay/{invoice}', [PayInvoiceController::class, 'index'])->name('pay.invoice');

// Merchant console mutations.
Route::post('/merchant/register', [MerchantController::class, 'register'])->name('merchant.register');
Route::put('/merchant/profile', [MerchantController::class, 'saveProfile'])->name('merchant.profile');
Route::post('/merchant/invoices', [MerchantController::class, 'createInvoice'])->name('merchant.invoice.create');
Route::post('/merchant/invoices/{id}/cancel', [MerchantController::class, 'cancelInvoice'])->name('merchant.invoice.cancel');
Route::post('/merchant/invoices/{id}/refund', [MerchantController::class, 'refundInvoice'])->name('merchant.invoice.refund');

// Invoice payment.
Route::post('/pay/{invoice}', [PayInvoiceController::class, 'pay'])->name('pay.execute');
