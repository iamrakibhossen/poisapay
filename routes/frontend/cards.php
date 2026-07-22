<?php

declare(strict_types=1);

use App\Http\Controllers\Frontend\CardManageController;
use App\Http\Controllers\Frontend\CardsController;
use Illuminate\Support\Facades\Route;

/*
 * Cards (list) + CardManage (detail) page group. Included from web.php inside the
 * authenticated group, so every route here already has the `auth` middleware.
 * Traditional server-rendered pages + form-POST mutations that redirect back.
 */

// Pages (server-rendered).
Route::get('/cards', [CardsController::class, 'index'])->name('cards');
Route::get('/cards/{card}', [CardManageController::class, 'index'])->name('cards.manage');

// Cards list mutations.
Route::post('/cards', [CardsController::class, 'generate'])->name('cards.generate');
Route::post('/cards/{card}/activate', [CardsController::class, 'activate'])->name('cards.activate');
Route::post('/cards/{card}/freeze', [CardsController::class, 'toggleFreeze'])->name('cards.freeze');

// Card management (detail) mutations.
Route::put('/cards/{card}/controls', [CardManageController::class, 'saveControls'])->name('card.controls');
Route::post('/cards/{card}/pin', [CardManageController::class, 'setPin'])->name('card.pin');
Route::post('/cards/{card}/manage-freeze', [CardManageController::class, 'toggleFreeze'])->name('card.freeze');
Route::post('/cards/{card}/replace', [CardManageController::class, 'replace'])->name('card.replace');
Route::post('/cards/{card}/close', [CardManageController::class, 'close'])->name('card.close');
Route::post('/cards/{card}/disputes', [CardManageController::class, 'submitDispute'])->name('card.dispute');
