<?php

declare(strict_types=1);

namespace App\Domain\Ramp\DTO;

/**
 * Terminal payout status delivered asynchronously by the processor's webhook,
 * normalised across vendors. Correlated back to a RampOrder by provider_ref.
 */
final class PayoutWebhookEvent
{
    /**
     * @param  string  $outcome  'succeeded' | 'failed'
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $providerRef,
        public readonly string $outcome,
        public readonly array $raw = [],
    ) {}

    public function succeeded(): bool
    {
        return $this->outcome === 'succeeded';
    }
}
