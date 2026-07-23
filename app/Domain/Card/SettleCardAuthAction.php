<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Rewards\AwardCashbackAction;
use App\Enums\CardAuthStatus;
use App\Enums\LedgerAccountType;
use App\Events\CardTransactionSettled;
use App\Models\Asset;
use App\Models\CardAuthorization;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Settle a card authorisation (TDD §F3.3 steps 7-8). The held crypto is realised
 * to the card program (treasury), a card fee accrues to fee:card, and any
 * over-hold (settlement < auth, e.g. no tip) is released to the user. Idempotent.
 */
class SettleCardAuthAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
        private readonly AwardCashbackAction $cashback,
    ) {}

    /** @param  Money|null  $settleAmount  crypto to actually settle (defaults to the full hold) */
    public function execute(CardAuthorization $auth, ?Money $settleAmount = null): CardAuthorization
    {
        if ($auth->status === CardAuthStatus::Settled) {
            return $auth;
        }

        return DB::transaction(function () use ($auth, $settleAmount): CardAuthorization {
            $auth = CardAuthorization::whereKey($auth->id)->lockForUpdate()->firstOrFail();
            if ($auth->status !== CardAuthStatus::Approved) {
                throw new RuntimeException('Only an approved authorisation can be settled.');
            }

            $asset = Asset::findOrFail($auth->funding_asset_id);
            $held = Money::ofBase($auth->held_amount, $asset->decimals, $asset->symbol);
            $settle = $settleAmount ?? $held;
            if ($settle->isGreaterThanOrEqual($held) && ! $settle->equals($held)) {
                throw new RuntimeException('Settlement cannot exceed the authorised hold.');
            }

            $card = $auth->card;
            $userId = $card->user_id;

            // Card fee (bps of the settled crypto).
            $feeBps = (int) getSetting('card_fee_bps', 100);
            $fee = Money::ofBase(BigInteger::of($settle->baseString())->multipliedBy($feeBps)->dividedBy(10_000), $asset->decimals, $asset->symbol);
            $toTreasury = $settle->minus($fee);
            $overHold = $held->minus($settle);

            $cardHold = $this->accounts->forUser($userId, LedgerAccountType::UserCardHold, $asset->id);
            $available = $this->accounts->forUser($userId, LedgerAccountType::UserAvailable, $asset->id);
            $settlement = $this->accounts->system(LedgerAccountType::CardProgramSettlement, $asset->id);
            $feeAccount = $this->accounts->system(LedgerAccountType::FeeCard, $asset->id);

            $lines = [PostingLine::debit($cardHold->id, $asset->id, $held)];
            if ($toTreasury->isPositive()) {
                $lines[] = PostingLine::credit($settlement->id, $asset->id, $toTreasury);
            }
            if ($fee->isPositive()) {
                $lines[] = PostingLine::credit($feeAccount->id, $asset->id, $fee);
            }
            if ($overHold->isPositive()) {
                $lines[] = PostingLine::credit($available->id, $asset->id, $overHold);
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'card.settle',
                idempotencyKey: "card:settle:{$auth->network_auth_id}",
                lines: $lines,
                memo: "Card settlement {$auth->merchant}",
                metadata: ['authorization_id' => $auth->id],
            ));

            $auth->update(['status' => CardAuthStatus::Settled, 'settle_entry_id' => $entry->id]);
            ActivityLogger::log('card.settled', $auth, ['merchant' => $auth->merchant, 'amount' => $auth->amount]);

            // Spend cashback (no-op unless a live cashback campaign applies).
            $this->cashback->execute($card->user, $asset, $settle, "card:cashback:{$auth->network_auth_id}");

            // Notify the cardholder + surface in their activity feed.
            CardTransactionSettled::dispatch($auth->id);

            return $auth->refresh();
        });
    }
}
