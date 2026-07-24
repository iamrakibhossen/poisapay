<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Card\CardService;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\CardType;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\Card;
use App\Models\CardProvider;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Provisions a virtual/physical card. The actual issuance goes through the
 * provider-agnostic {@see CardService}; on top of it this action charges the
 * one-time issuance price ({@see CardPricing}) from the user's stablecoin
 * balance (user:available -> fee:card). Virtual and physical are priced
 * independently and a price of 0 issues for free.
 */
class GenerateCardAction
{
    public function __construct(
        private readonly CardService $cards,
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(User $user, CardProvider $provider, CardType $type): Card
    {
        $priceMinor = CardPricing::priceMinor($type);

        // Free card → issue directly, no charge.
        if ($priceMinor <= 0) {
            return $this->cards->issueCard($user, $provider, $type);
        }

        [$asset, $fee] = $this->feeInFundingAsset($priceMinor);

        // Fail fast before touching the provider if the balance can't cover it.
        if ($this->ledger->availableBalance($user, $asset->id)->isLessThan($fee)) {
            throw new RuntimeException(
                __('You need at least :amount to buy this card.', ['amount' => $asset->symbol.' '.$fee->format()])
            );
        }

        $card = $this->cards->issueCard($user, $provider, $type);

        try {
            $this->charge($user, $card, $asset, $fee);
        } catch (RuntimeException $e) {
            // Balance moved between the pre-check and the (locked) charge — undo the
            // just-issued card so the user is never left with an unpaid card.
            $card->delete();
            throw $e;
        }

        return $card;
    }

    /** Debit user:available -> credit fee:card, re-checking the balance under a row lock. */
    private function charge(User $user, Card $card, Asset $asset, Money $fee): void
    {
        DB::transaction(function () use ($user, $card, $asset, $fee): void {
            $available = $this->accounts->forUser($user->id, LedgerAccountType::UserAvailable, $asset->id);
            $feeCard = $this->accounts->system(LedgerAccountType::FeeCard, $asset->id);

            $row = DB::table('account_balances')->where('account_id', $available->id)->lockForUpdate()->first();
            $current = Money::ofBase($row->balance ?? '0', $asset->decimals, $asset->symbol);
            if ($current->isLessThan($fee)) {
                throw new RuntimeException(
                    __('You need at least :amount to buy this card.', ['amount' => $asset->symbol.' '.$fee->format()])
                );
            }

            $this->ledger->post(new EntryData(
                type: 'card.issue.fee',
                idempotencyKey: "card:issue:fee:{$card->id}",
                lines: [
                    PostingLine::debit($available->id, $asset->id, $fee->baseString()),
                    PostingLine::credit($feeCard->id, $asset->id, $fee->baseString()),
                ],
                memo: "Card issuance ({$card->type->value})",
                metadata: ['card_id' => $card->id, 'price_minor' => CardPricing::priceMinor($card->type)],
            ));
        });
    }

    /**
     * Convert a settlement-currency price (minor, 2dp) into the stablecoin funding
     * asset's base units — a value-of-USD proxy, matching card authorisation.
     *
     * @return array{0: Asset, 1: Money}
     */
    private function feeInFundingAsset(int $priceMinor): array
    {
        $asset = Asset::where('symbol', CardPricing::fundingAsset())->where('is_active', true)->first();
        if (! $asset) {
            throw new RuntimeException(__('Card purchases are temporarily unavailable.'));
        }

        // 2dp fiat minor -> asset base units (e.g. USDT 6dp: scale up by 4).
        $base = (string) ($priceMinor * 10 ** ($asset->decimals - 2));

        return [$asset, Money::ofBase($base, $asset->decimals, $asset->symbol)];
    }
}
