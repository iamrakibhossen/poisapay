<?php

declare(strict_types=1);

namespace App\Card\DTOs;

use App\Card\Enums\WebhookEventType;
use Carbon\CarbonImmutable;

/**
 * A provider event (webhook OR simulated) normalised to one canonical shape.
 * providerEventId is the dedupe key — the inbound pipeline drops repeats.
 */
final readonly class NormalizedWebhookEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $provider,          // driver key (mock|marqeta|…)
        public WebhookEventType $type,
        public string $providerEventId,
        public ?string $providerCardRef = null,
        public ?string $providerTxRef = null,
        public ?string $amountMinor = null,
        public ?string $currency = null,
        public ?CarbonImmutable $occurredAt = null,
        public array $payload = [],
    ) {}
}
