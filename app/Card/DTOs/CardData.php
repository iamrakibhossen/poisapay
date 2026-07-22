<?php

declare(strict_types=1);

namespace App\Card\DTOs;

use App\Enums\CardNetwork;
use App\Enums\CardStatus;
use App\Enums\CardType;

/**
 * Provider-neutral view of an issued card. pan/cvv are populated ONLY on an
 * explicit reveal call and are NEVER persisted (cards.ck_no_pan forbids it).
 */
final readonly class CardData
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $providerCardRef,   // provider card token
        public CardType $type,
        public CardNetwork $network,
        public CardStatus $status,
        public ?string $last4 = null,
        public ?int $expMonth = null,
        public ?int $expYear = null,
        public ?string $cardholderRef = null,
        public ?string $pan = null,       // reveal-only, transient
        public ?string $cvv = null,       // reveal-only, transient
        public array $raw = [],
    ) {}
}
