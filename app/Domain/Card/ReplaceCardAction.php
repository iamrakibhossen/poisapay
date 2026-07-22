<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Card\CardService;
use App\Domain\Audit\ActivityLogger;
use App\Enums\CardStatus;
use App\Models\Card;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Replace a lost / stolen / damaged card (TDD §F3.2). The old card is closed and
 * a fresh card is issued through the same provider, inheriting its controls and
 * limits. The old record keeps a replaced_by pointer for the audit trail.
 */
class ReplaceCardAction
{
    public function __construct(
        private readonly GenerateCardAction $generate,
        private readonly CardService $cards,
    ) {}

    public function execute(Card $card, string $reason = 'lost'): Card
    {
        if ($card->status === CardStatus::Closed) {
            throw new RuntimeException('A closed card cannot be replaced.');
        }

        return DB::transaction(function () use ($card, $reason): Card {
            $provider = $card->provider;
            if (! $provider) {
                throw new RuntimeException('The card has no associated provider to reissue from.');
            }

            $replacement = $this->generate->execute($card->user, $provider, $card->type);

            // Carry over the cardholder's controls and limits.
            $replacement->update([
                'nickname' => $card->nickname,
                'daily_limit' => $card->daily_limit,
                'per_tx_limit' => $card->per_tx_limit,
                'online_enabled' => $card->online_enabled,
                'atm_enabled' => $card->atm_enabled,
                'contactless_enabled' => $card->contactless_enabled,
                'allowed_countries' => $card->allowed_countries,
                'blocked_mccs' => $card->blocked_mccs,
                'status' => CardStatus::Active,
            ]);

            $this->cards->terminate($card, $reason);

            $card->update([
                'status' => CardStatus::Closed,
                'replaced_by' => $replacement->id,
                'closed_at' => now(),
            ]);

            ActivityLogger::log('card.replaced', $card, [
                'reason' => $reason,
                'replacement_id' => $replacement->id,
            ]);

            return $replacement->refresh();
        });
    }
}
