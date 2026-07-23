<?php

declare(strict_types=1);

namespace App\Domain\Ramp\DTO;

/**
 * Instruction to a fiat payout processor (off-ramp). Amounts are minor-unit
 * strings (paisa/cents) to preserve exactness across the adapter boundary.
 */
final class PayoutRequest
{
    /**
     * @param  array<string, mixed>  $details  rail-specific destination fields (account no, wallet msisdn, …)
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $currency,
        public readonly string $amount,
        public readonly string $rail,
        public readonly ?string $beneficiary = null,
        public readonly array $details = [],
    ) {}
}
