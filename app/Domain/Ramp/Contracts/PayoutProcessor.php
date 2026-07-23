<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Contracts;

use App\Domain\Ramp\DTO\PayoutRequest;
use App\Domain\Ramp\DTO\PayoutResult;
use App\Domain\Ramp\DTO\PayoutWebhookEvent;
use Illuminate\Http\Request;

/**
 * Fiat payout processor for crypto→fiat off-ramps (TDD §F1.3). The stub settles
 * on command; a real PSP (Wise, Flutterwave, a bank rail) implements this and is
 * selected via config/providers.php. Payouts are async: submit now, get a
 * terminal outcome later on the webhook.
 */
interface PayoutProcessor
{
    /** Stable processor identifier, persisted on the ramp order. */
    public function name(): string;

    /** Submit a payout instruction; returns the provider reference to track it. */
    public function initiatePayout(PayoutRequest $request): PayoutResult;

    /** Authenticate an inbound webhook (signature / shared secret). */
    public function verifyWebhook(Request $request): bool;

    /** Normalise an inbound webhook payload into a neutral event. */
    public function parseWebhook(Request $request): PayoutWebhookEvent;
}
