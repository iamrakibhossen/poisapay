<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Card\CardService;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Spending\DTO\SpendRequest;
use App\Domain\Spending\Enums\SpendPurpose;
use App\Domain\Spending\SpendingEngine;
use App\Enums\AssetKind;
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
        private readonly SpendingEngine $spending,
    ) {}

    public function execute(User $user, CardProvider $provider, CardType $type): Card
    {
        $priceMinor = CardPricing::priceMinor($type);

        // Free card → issue directly, no charge.
        if ($priceMinor <= 0) {
            return $this->cards->issueCard($user, $provider, $type);
        }

        [$asset, $fee] = $this->feeInFundingAsset($priceMinor);

        // Fail fast before touching the provider if the balance can't cover it. With
        // the Spending Engine on the fee can be sourced from any balance (auto-
        // converted), so this funding-asset-only pre-check is skipped — the engine
        // validates during the charge and the catch below rolls back the card.
        if (! feature('spending_engine_enabled', false)
            && $this->ledger->availableBalance($user, $asset->id)->isLessThan($fee)) {
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
        $memo = "Card issuance ({$card->type->value})";
        $metadata = ['card_id' => $card->id, 'price_minor' => CardPricing::priceMinor($card->type)];

        // Single source of truth for spends: source the fee from the user's spending
        // priority (any balance, auto-converted to the funding asset). fee:card still
        // accrues in the funding asset, so revenue accounting is unchanged.
        if (feature('spending_engine_enabled', false)) {
            $feeCard = $this->accounts->system(LedgerAccountType::FeeCard, $asset->id);

            $this->spending->spend(new SpendRequest(
                user: $user,
                settlementAsset: $asset,
                amount: $fee,
                purpose: SpendPurpose::CardPurchase,
                destination: [PostingLine::credit($feeCard->id, $asset->id, $fee)],
                idempotencyKey: "card:issue:fee:{$card->id}",
                memo: $memo,
                metadata: $metadata,
            ));

            return;
        }

        DB::transaction(function () use ($user, $card, $asset, $fee, $memo, $metadata): void {
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
                memo: $memo,
                metadata: $metadata,
            ));
        });
    }

    /**
     * The asset the fee settles in, and the fee amount. The card is priced in a
     * fiat currency (USD), so settle in THAT fiat asset when it exists — a user
     * holding USD then pays 1:1 with no conversion. Only when no matching fiat
     * asset is seeded do we fall back to the configured stablecoin proxy (USDT).
     * The Spending Engine still lets a user without the settlement asset fund it
     * from any balance (auto-converted), so this never blocks a purchase.
     *
     * @return array{0: Asset, 1: Money}
     */
    private function feeInFundingAsset(int $priceMinor): array
    {
        $currency = CardPricing::currency();
        $asset = Asset::where('is_active', true)->where('kind', AssetKind::Fiat->value)
            ->where(fn ($q) => $q->where('symbol', $currency)->orWhere('currency_code', $currency))
            ->orderBy('id')->first()
            ?? Asset::where('symbol', CardPricing::fundingAsset())->where('is_active', true)->orderBy('id')->first();

        if (! $asset) {
            throw new RuntimeException(__('Card purchases are temporarily unavailable.'));
        }

        // Price minor (2dp) -> asset base units (fiat 2dp: 1:1; USDT 6dp: scale by 4).
        $base = (string) ($priceMinor * 10 ** ($asset->decimals - 2));

        return [$asset, Money::ofBase($base, $asset->decimals, $asset->symbol)];
    }
}
