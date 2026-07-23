<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Payout;

use App\Domain\Ramp\Contracts\PayoutProcessor;
use App\Domain\Ramp\DTO\PayoutRequest;
use App\Domain\Ramp\DTO\PayoutResult;
use App\Domain\Ramp\DTO\PayoutWebhookEvent;
use Illuminate\Http\Request;

/**
 * Deterministic payout stub. Accepts every instruction and returns a stable
 * provider reference derived from the order id; terminal settlement is driven by
 * a signed webhook (see the off-ramp test / an operator simulation). Verifies the
 * webhook via HMAC when a secret is configured, and accepts unsigned calls in
 * local/testing where no secret is set.
 */
final class StubPayoutProcessor implements PayoutProcessor
{
    public function name(): string
    {
        return 'stub';
    }

    public function initiatePayout(PayoutRequest $request): PayoutResult
    {
        return new PayoutResult(
            providerRef: 'stub_'.substr(hash('sha256', $request->orderId), 0, 24),
            status: 'submitted',
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('providers.payout.webhook_secret', '');
        if ($secret === '') {
            return true; // local / testing: no secret configured
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, (string) $request->header('X-Payout-Signature'));
    }

    public function parseWebhook(Request $request): PayoutWebhookEvent
    {
        return new PayoutWebhookEvent(
            providerRef: (string) $request->input('provider_ref'),
            outcome: $request->input('outcome') === 'succeeded' ? 'succeeded' : 'failed',
            raw: (array) $request->all(),
        );
    }
}
