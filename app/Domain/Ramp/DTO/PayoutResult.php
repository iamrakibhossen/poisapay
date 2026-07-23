<?php

declare(strict_types=1);

namespace App\Domain\Ramp\DTO;

/**
 * Synchronous acknowledgement from a payout processor when a payout is submitted.
 * Terminal success/failure arrives later via {@see PayoutWebhookEvent}.
 */
final class PayoutResult
{
    /**
     * @param  string  $status  'submitted' | 'failed'
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $providerRef,
        public readonly string $status,
        public readonly array $raw = [],
    ) {}

    public function failed(): bool
    {
        return $this->status === 'failed';
    }
}
