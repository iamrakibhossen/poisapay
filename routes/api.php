<?php

declare(strict_types=1);

use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepositController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\SecurityController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Card\CardInboundController;
use App\Http\Controllers\Webhook\PayoutWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * API documentation (OpenAPI 3 spec + Swagger UI).
 */
Route::controller(OpenApiController::class)->group(function () {
    Route::get('openapi.json', 'spec')->name('api.openapi');
    Route::get('docs', 'ui')->name('api.docs');
});

/*
 * Inbound webhooks (provider-agnostic, unauthenticated — verified per provider). The
 * `webhook.log` middleware records every request + response into webhook_logs for
 * audit/debug/replay. Card webhooks are deduped + queued; JIT funding is answered
 * synchronously by the ledger; payout webhooks apply the terminal outcome idempotently.
 */
Route::middleware('webhook.log')->group(function () {
    Route::controller(CardInboundController::class)->group(function () {
        Route::post('card/webhooks/{provider}', 'webhook')->middleware('throttle:240,1');
        Route::post('card/jit/{provider}', 'jit')->middleware('throttle:600,1');
    });

    Route::post('ramp/payout/webhook/{driver}', [PayoutWebhookController::class, 'handle'])->middleware('throttle:240,1');
});

/*
 * PoisaPay REST API v1 (TDD §8). Bearer token (Sanctum) for users; mutating
 * money endpoints accept an Idempotency-Key header. Sensitive endpoints are
 * rate-limited more tightly.
 */
Route::prefix('v1')->group(function () {
    // Public auth.
    Route::controller(AuthController::class)->group(function () {
        Route::post('auth/register', 'register')->middleware('throttle:10,1');
        Route::post('auth/login', 'login')->middleware('throttle:10,1');
        Route::post('auth/2fa/verify', 'twoFactorVerify')->middleware('throttle:10,1');
    });

    // Authenticated.
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::controller(AuthController::class)->group(function () {
            Route::get('auth/me', 'me');
            Route::post('auth/logout', 'logout');
        });

        Route::controller(ReferenceController::class)->group(function () {
            Route::get('assets', 'assets');
            Route::get('chains', 'chains');
        });

        Route::controller(WalletController::class)->group(function () {
            Route::get('wallets', 'index');
            Route::get('wallets/{symbol}', 'show');
        });

        Route::controller(DepositController::class)->group(function () {
            Route::post('deposit-addresses', 'createAddress');
            Route::get('deposits', 'index');
        });

        Route::controller(TransferController::class)->group(function () {
            Route::post('transfers', 'store')->middleware('throttle:30,1');
            Route::get('transfers', 'index');
        });

        // Security centre (Wave 4).
        Route::controller(SecurityController::class)->group(function () {
            Route::get('security/addresses', 'addresses');
            Route::post('security/addresses', 'storeAddress')->middleware('throttle:sensitive');
            Route::delete('security/addresses/{id}', 'destroyAddress')->middleware('throttle:sensitive');
            Route::get('security/events', 'events');
            Route::get('security/login-history', 'loginHistory');
            Route::post('push-tokens', 'registerPushToken')->middleware('throttle:sensitive');
            Route::delete('push-tokens', 'deletePushToken');
        });
    });
});
