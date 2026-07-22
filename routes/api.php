<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepositController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Card\CardInboundController;
use Illuminate\Support\Facades\Route;

/*
 * Card provider inbound (provider-agnostic, unauthenticated — verified per provider).
 * Webhooks are deduped + queued; JIT funding is answered synchronously by the ledger.
 */
Route::post('card/webhooks/{provider}', [CardInboundController::class, 'webhook'])->middleware('throttle:240,1');
Route::post('card/jit/{provider}', [CardInboundController::class, 'jit'])->middleware('throttle:600,1');

/*
 * PoisaPay REST API v1 (TDD §8). Bearer token (Sanctum) for users; mutating
 * money endpoints accept an Idempotency-Key header. Sensitive endpoints are
 * rate-limited more tightly.
 */
Route::prefix('v1')->group(function () {
    // Public
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/2fa/verify', [AuthController::class, 'twoFactorVerify'])->middleware('throttle:10,1');

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('assets', [ReferenceController::class, 'assets']);
        Route::get('chains', [ReferenceController::class, 'chains']);

        Route::get('wallets', [WalletController::class, 'index']);
        Route::get('wallets/{symbol}', [WalletController::class, 'show']);

        Route::post('deposit-addresses', [DepositController::class, 'createAddress']);
        Route::get('deposits', [DepositController::class, 'index']);

        Route::post('transfers', [TransferController::class, 'store'])->middleware('throttle:30,1');
        Route::get('transfers', [TransferController::class, 'index']);
    });
});
