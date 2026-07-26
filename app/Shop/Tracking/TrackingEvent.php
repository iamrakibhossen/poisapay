<?php

declare(strict_types=1);

namespace App\Shop\Tracking;

/**
 * A single normalized tracking event: a type + a flat payload. The payload uses
 * canonical keys (value, currency, product_id, product_name, quantity, order_id,
 * email, country, …) that every adapter reshapes into its own required format.
 */
final readonly class TrackingEvent
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public TrackingEventType $type,
        public array $payload = [],
    ) {}

    /** @param  array<string, mixed>  $payload */
    public static function of(TrackingEventType $type, array $payload = []): self
    {
        // Drop null/empty so a provider never receives blank fields (privacy + noise).
        return new self($type, array_filter($payload, static fn ($v) => $v !== null && $v !== ''));
    }

    /** @return array{type: string, data: array<string, mixed>} */
    public function toArray(): array
    {
        return ['type' => $this->type->value, 'data' => $this->payload];
    }
}
