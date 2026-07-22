<?php

declare(strict_types=1);

namespace App\Card\DTOs;

/** Carries the provider's cardholder token back to our layer. */
final readonly class CardholderResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $providerRef,       // provider cardholder token (e.g. Marqeta user_token)
        public ?string $status = null,
        public array $raw = [],
    ) {}
}
