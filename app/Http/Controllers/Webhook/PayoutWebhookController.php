<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Domain\Ramp\Contracts\PayoutProcessor;
use App\Domain\Ramp\SettleOffRampAction;
use App\Enums\RampDirection;
use App\Http\Controllers\Controller;
use App\Models\RampOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound fiat-payout webhook (provider-agnostic). The configured PSP verifies
 * the signature and normalises the payload; we correlate by provider_ref and
 * apply the terminal outcome idempotently. Unknown refs are acked so the PSP
 * stops retrying.
 */
class PayoutWebhookController extends Controller
{
    public function handle(Request $request, PayoutProcessor $psp, SettleOffRampAction $settle): JsonResponse
    {
        if (! $psp->verifyWebhook($request)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $event = $psp->parseWebhook($request);

        $order = RampOrder::where('direction', RampDirection::Off->value)
            ->where('provider_ref', $event->providerRef)
            ->first();

        if ($order) {
            $event->succeeded() ? $settle->settle($order) : $settle->fail($order);
        }

        return response()->json(['ok' => true]);
    }
}
