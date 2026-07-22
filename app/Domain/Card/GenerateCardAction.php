<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Card\CardService;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardProvider;
use App\Models\User;

/**
 * Provisions a virtual/physical card. Kept as a thin domain entry point; the
 * actual issuance goes through the provider-agnostic {@see CardService}, which
 * calls the resolved provider adapter (mock, Marqeta, …) and persists the card.
 */
class GenerateCardAction
{
    public function __construct(private readonly CardService $cards) {}

    public function execute(User $user, CardProvider $provider, CardType $type): Card
    {
        return $this->cards->issueCard($user, $provider, $type);
    }
}
