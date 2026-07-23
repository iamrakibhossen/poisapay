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

// Cards list page + its mutations.
Route::controller(CardsController::class)->group(function () {
    Route::get('/cards', 'index')->name('cards');
    Route::post('/cards', 'generate')->name('cards.generate');
    Route::post('/cards/{card}/activate', 'activate')->name('cards.activate');
    Route::post('/cards/{card}/freeze', 'toggleFreeze')->name('cards.freeze');
});

// Card management (detail) page + its mutations.
Route::controller(CardManageController::class)->group(function () {
    Route::get('/cards/{card}', 'index')->name('cards.manage');
    Route::put('/cards/{card}/controls', 'saveControls')->name('card.controls');
    Route::post('/cards/{card}/pin', 'setPin')->name('card.pin');
    Route::post('/cards/{card}/manage-freeze', 'toggleFreeze')->name('card.freeze');
    Route::post('/cards/{card}/replace', 'replace')->name('card.replace');
    Route::post('/cards/{card}/close', 'close')->name('card.close');
    Route::post('/cards/{card}/disputes', 'submitDispute')->name('card.dispute');
});
