<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Card\CardService;
use App\Domain\Audit\ActivityLogger;
use App\Enums\CardAuthStatus;
use App\Enums\CardStatus;
use App\Models\Card;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permanently close a card (TDD §F3.2). Refused while an authorisation is still
 * held (un-settled) so no in-flight spend is orphaned. Terminal — a closed card
 * can only be replaced, never reopened. The provider card is terminated too.
 */
class CloseCardAction
{
    public function __construct(private readonly CardService $cards) {}

    public function execute(Card $card, string $reason = 'user_requested'): Card
    {
        if ($card->status === CardStatus::Closed) {
            return $card;
        }

        return DB::transaction(function () use ($card, $reason): Card {
            $pendingHolds = $card->authorizations()->where('status', CardAuthStatus::Approved)->exists();
            if ($pendingHolds) {
                throw new RuntimeException('Settle or reverse all pending authorisations before closing this card.');
            }

            $this->cards->terminate($card, $reason);

            $card->update([
                'status' => CardStatus::Closed,
                'closed_at' => now(),
            ]);

            ActivityLogger::log('card.closed', $card, ['reason' => $reason]);

            return $card->refresh();
        });
    }
}
