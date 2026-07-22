<?php

declare(strict_types=1);

namespace App\Domain\Webhook;

use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

/**
 * Outbound webhooks (TDD §8.3). For each active endpoint subscribed to an event,
 * a delivery is recorded and queued; DispatchWebhookJob signs and sends it with
 * HMAC + retry/backoff. Deliveries are logged for audit and replay.
 */
class WebhookService
{
    /** Fan an event out to a specific owner's (merchant's) subscribed endpoints. */
    public function dispatch(string $ownerId, string $event, array $payload): void
    {
        WebhookEndpoint::where('user_id', $ownerId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $e) => in_array($event, (array) $e->events, true))
            ->each(function (WebhookEndpoint $endpoint) use ($event, $payload) {
                $delivery = WebhookDelivery::create([
                    'endpoint_id' => $endpoint->id,
                    'event' => $event,
                    'payload' => $payload,
                    'attempt' => 0,
                    'status' => 'pending',
                ]);

                DispatchWebhookJob::dispatch($delivery->id);
            });
    }

    /** HMAC-SHA256 signature of the raw body with the endpoint secret. */
    public static function sign(string $body, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $secret);
    }
}
