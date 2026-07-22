<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\CardAuthStatus;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\CardAuthorization;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reverse an approved (un-settled) authorisation — releases the hold
 * user:card_hold -> user:available. Idempotent by network_auth_id.
 */
class ReverseCardAuthAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(CardAuthorization $auth): CardAuthorization
    {
        if ($auth->status === CardAuthStatus::Reversed) {
            return $auth;
        }

        return DB::transaction(function () use ($auth): CardAuthorization {
            $auth = CardAuthorization::whereKey($auth->id)->lockForUpdate()->firstOrFail();
            if ($auth->status !== CardAuthStatus::Approved) {
                throw new RuntimeException('Only an approved authorisation can be reversed.');
            }

            $asset = Asset::findOrFail($auth->funding_asset_id);
            $held = Money::ofBase($auth->held_amount, $asset->decimals, $asset->symbol);

            $cardHold = $this->accounts->forUser($auth->card->user_id, LedgerAccountType::UserCardHold, $asset->id);
            $available = $this->accounts->forUser($auth->card->user_id, LedgerAccountType::UserAvailable, $asset->id);

            $entry = $this->ledger->post(new EntryData(
                type: 'card.reverse',
                idempotencyKey: "card:reverse:{$auth->network_auth_id}",
                lines: [
                    PostingLine::debit($cardHold->id, $asset->id, $held),
                    PostingLine::credit($available->id, $asset->id, $held),
                ],
                memo: "Card auth reversal {$auth->merchant}",
                metadata: ['authorization_id' => $auth->id],
            ));

            $auth->update(['status' => CardAuthStatus::Reversed, 'settle_entry_id' => $entry->id]);
            ActivityLogger::log('card.reversed', $auth, ['merchant' => $auth->merchant]);

            return $auth->refresh();
        });
    }
}
