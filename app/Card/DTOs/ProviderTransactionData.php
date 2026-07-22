<?php

declare(strict_types=1);

namespace App\Card\DTOs;

use Carbon\CarbonImmutable;

/** Provider-neutral transaction row (from getTransactions / syncTransactions). */
final readonly class ProviderTransactionData
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $providerTxRef,
        public string $type,              // authorization | clearing | refund | reversal
        public string $amountMinor,       // settlement-currency minor units
        public string $currency,
        public string $status,
        public ?string $mcc = null,
        public ?string $merchant = null,
        public ?CarbonImmutable $occurredAt = null,
        public array $raw = [],
    ) {}
}
