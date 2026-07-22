<?php

declare(strict_types=1);

namespace App\Domain\Card;

/** Inbound network authorisation payload (TDD §F3.3 step 1). */
final readonly class CardAuthorizationRequest
{
    public function __construct(
        public string $cardRef,
        public string $networkAuthId,
        public string $amountMinor,   // settlement-currency minor units
        public string $currency,
        public ?string $mcc = null,
        public ?string $merchant = null,
        public string $channel = 'online',   // online | atm | contactless | pos
        public ?string $country = null,      // merchant ISO-3166-1 alpha-2
    ) {}
}
