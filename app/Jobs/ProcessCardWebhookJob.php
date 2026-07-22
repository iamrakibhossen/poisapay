<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Card\DTOs\NormalizedWebhookEvent;
use App\Card\Enums\WebhookEventType;
use App\Card\Inbound\WebhookEventRouter;
use App\Models\CardWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Processes one deduped provider event off-request; retries with backoff. */
class ProcessCardWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public string $webhookId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }

    public function handle(WebhookEventRouter $router): void
    {
        $webhook = CardWebhook::find($this->webhookId);
        if (! $webhook || $webhook->status === 'processed') {
            return;
        }

        $webhook->increment('attempts');

        try {
            $payload = $webhook->payload ?? [];
            $event = new NormalizedWebhookEvent(
                provider: $webhook->driver,
                type: WebhookEventType::tryFrom($webhook->event_type) ?? WebhookEventType::Unknown,
                providerEventId: $webhook->provider_event_id,
                providerCardRef: $webhook->provider_card_ref,
                providerTxRef: $webhook->provider_tx_ref,
                amountMinor: $payload['amount_minor'] ?? null,
                currency: $payload['currency'] ?? null,
                payload: $payload['raw'] ?? [],
            );

            $outcome = $router->handle($event);
            $webhook->update(['status' => $outcome, 'processed_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $webhook->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 255)]);
            throw $e;
        }
    }
}
