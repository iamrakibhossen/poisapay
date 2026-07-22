<?php

declare(strict_types=1);

namespace App\Card\DTOs;

/** Provider-neutral cardholder input (mapped from our User). */
final readonly class CardholderData
{
    /** @param array<string, mixed> $address @param array<string, mixed> $metadata */
    public function __construct(
        public string $externalId,        // our User id — the provider's external reference
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public ?string $phone = null,
        public array $address = [],
        public array $metadata = [],
    ) {}
}
