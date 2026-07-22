<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Webhook\WebhookService;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Deliver a single webhook (TDD §8.3): sign with HMAC, POST with a timeout, and
 * record the outcome. Retries with exponential backoff on failure.
 */
class DispatchWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $deliveryId) {}

    /** Backoff schedule in seconds (exponential). */
    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);
        if (! $delivery || $delivery->status === 'delivered' || ! $delivery->endpoint) {
            return;
        }

        $endpoint = $delivery->endpoint;
        $body = json_encode([
            'id' => $delivery->id,
            'event' => $delivery->event,
            'data' => $delivery->payload,
            'created_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES);

        $delivery->increment('attempt');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-PoisaPay-Event' => $delivery->event,
                    'X-PoisaPay-Signature' => WebhookService::sign($body, $endpoint->secret),
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $delivery->update([
                'response_status' => $response->status(),
                'status' => $response->successful() ? 'delivered' : 'failed',
                'next_retry_at' => $response->successful() ? null : now()->addSeconds($this->backoff()[$delivery->attempt - 1] ?? 600),
            ]);

            if (! $response->successful()) {
                $this->release($this->backoff()[$delivery->attempt - 1] ?? 600);
            }
        } catch (\Throwable $e) {
            $delivery->update(['status' => 'failed', 'next_retry_at' => now()->addSeconds($this->backoff()[$delivery->attempt - 1] ?? 600)]);
            throw $e; // let the queue retry within $tries
        }
    }
}
