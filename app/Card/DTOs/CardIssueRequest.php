<?php

declare(strict_types=1);

namespace App\Card\DTOs;

use App\Enums\CardNetwork;
use App\Enums\CardType;

/** Provider-neutral request to issue one card for an existing cardholder. */
final readonly class CardIssueRequest
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $cardholderRef,     // provider cardholder token
        public CardType $type,
        public string $program,           // card program / product slug
        public CardNetwork $network = CardNetwork::Visa,
        public string $currency = 'USD',
        public ?string $nickname = null,
        public array $metadata = [],
    ) {}
}
