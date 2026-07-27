<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Rewards\AwardCashbackAction;
use App\Enums\AssetKind;
use App\Enums\CardAuthStatus;
use App\Enums\LedgerAccountType;
use App\Events\CardTransactionSettled;
use App\Models\Asset;
use App\Models\CardAuthorization;
use App\Models\JournalEntry;
use App\Support\Money;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Settle a card authorisation (TDD §F3.3 steps 7-8). The held funds are realised
 * to the card program (settlement account), a card fee accrues to fee:card, and
 * any over-hold (settlement < auth, e.g. no tip) is released to the user. When the
 * card was funded from crypto the held crypto is converted to the merchant's fiat
 * currency here (an internal FX conversion — see {@see settleWithFxConversion()}),
 * so the merchant is always settled in the requested fiat. Idempotent.
 */
class SettleCardAuthAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
        private readonly AwardCashbackAction $cashback,
    ) {}

    /** @param  Money|null  $settleAmount  funding-asset amount to settle (defaults to the full hold) */
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
            $feeBps = (int) getSetting('card_fee_bps', 100);

            // Crypto-funded charges convert to the merchant's fiat here; same-fiat
            // (or crypto with no matching fiat asset) settle directly in place.
            $fiat = $this->settlementFiat($auth, $asset);
            $entry = $fiat
                ? $this->settleWithFxConversion($auth, $userId, $asset, $held, $settle, $fiat, $feeBps)
                : $this->settleInPlace($auth, $userId, $asset, $held, $settle, $feeBps);

            $auth->update(['status' => CardAuthStatus::Settled, 'settle_entry_id' => $entry->id]);
            ActivityLogger::log('card.settled', $auth, ['merchant' => $auth->merchant, 'amount' => $auth->amount]);

            // Spend cashback (no-op unless a live cashback campaign applies).
            $this->cashback->execute($card->user, $asset, $settle, "card:cashback:{$auth->network_auth_id}");

            // Notify the cardholder + surface in their activity feed.
            CardTransactionSettled::dispatch($auth->id);

            return $auth->refresh();
        });
    }

    /**
     * The fiat asset the merchant is settled in — only when the card was funded
     * from a DIFFERENT (crypto) asset, so a conversion is actually required.
     * Returns null for same-fiat funding or when the fiat currency has no asset
     * (then we settle the held asset in place, the legacy crypto-parking model).
     */
    private function settlementFiat(CardAuthorization $auth, Asset $funding): ?Asset
    {
        if ($funding->isFiat()) {
            return null; // funded directly in a fiat wallet — settle in place
        }

        $currency = strtoupper((string) $auth->currency_code);

        $fiat = Asset::where('is_active', true)
            ->where('kind', AssetKind::Fiat->value)
            ->where(fn ($q) => $q->where('symbol', $currency)->orWhere('currency_code', $currency))
            ->orderBy('id')->first();

        return $fiat && $fiat->id !== $funding->id ? $fiat : null;
    }

    /** Legacy in-place settlement: the held asset is realised to the card program as-is. */
    private function settleInPlace(CardAuthorization $auth, string $userId, Asset $asset, Money $held, Money $settle, int $feeBps): JournalEntry
    {
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

        return $this->ledger->post(new EntryData(
            type: 'card.settle',
            idempotencyKey: "card:settle:{$auth->network_auth_id}",
            lines: $lines,
            memo: "Card settlement {$auth->merchant}",
            metadata: ['authorization_id' => $auth->id],
        ));
    }

    /**
     * Crypto-funded settlement with an internal FX conversion to the merchant fiat.
     * The held crypto (minus any over-hold released back to the user and the card
     * fee) is taken into the house dealer position; the equivalent fiat is credited
     * to the card program settlement account (dealer:inventory[fiat] carries the
     * house FX position). Each per-asset leg stays balanced, and the merchant is
     * settled in the requested fiat currency.
     */
    private function settleWithFxConversion(CardAuthorization $auth, string $userId, Asset $asset, Money $held, Money $settle, Asset $fiat, int $feeBps): JournalEntry
    {
        $fee = Money::ofBase(BigInteger::of($settle->baseString())->multipliedBy($feeBps)->dividedBy(10_000), $asset->decimals, $asset->symbol);
        $toDealer = $settle->minus($fee);       // crypto the house converts
        $overHold = $held->minus($settle);      // unsettled portion returned to user

        // Fiat owed to the merchant, proportional to the settled fraction of the
        // hold (full clear ⇒ the whole authorised fiat amount).
        $fiatMinor = BigInteger::of((string) $auth->amount)
            ->multipliedBy(BigInteger::of($settle->baseString()))
            ->dividedBy(BigInteger::of($held->baseString()), RoundingMode::DOWN);
        $fiatBase = $fiatMinor->multipliedBy(BigInteger::of(10)->power(max(0, $fiat->decimals - 2)));
        $fiatOwed = Money::ofBase((string) $fiatBase, $fiat->decimals, $fiat->symbol);

        $cardHold = $this->accounts->forUser($userId, LedgerAccountType::UserCardHold, $asset->id);
        $available = $this->accounts->forUser($userId, LedgerAccountType::UserAvailable, $asset->id);
        $feeAccount = $this->accounts->system(LedgerAccountType::FeeCard, $asset->id);
        $dealerCrypto = $this->accounts->system(LedgerAccountType::TradingInventory, $asset->id);
        $dealerFiat = $this->accounts->system(LedgerAccountType::TradingInventory, $fiat->id);
        $settlement = $this->accounts->system(LedgerAccountType::CardProgramSettlement, $fiat->id);

        // Crypto leg — the held crypto is fully cleared (converted + fee + release).
        $lines = [PostingLine::debit($cardHold->id, $asset->id, $held)];
        if ($toDealer->isPositive()) {
            $lines[] = PostingLine::credit($dealerCrypto->id, $asset->id, $toDealer);
        }
        if ($fee->isPositive()) {
            $lines[] = PostingLine::credit($feeAccount->id, $asset->id, $fee);
        }
        if ($overHold->isPositive()) {
            $lines[] = PostingLine::credit($available->id, $asset->id, $overHold);
        }

        // Fiat leg — the house sources the merchant fiat (its FX position) and
        // credits the settlement account. Balanced within the fiat asset.
        if ($fiatOwed->isPositive()) {
            $lines[] = PostingLine::debit($dealerFiat->id, $fiat->id, $fiatOwed);
            $lines[] = PostingLine::credit($settlement->id, $fiat->id, $fiatOwed);
        }

        return $this->ledger->post(new EntryData(
            type: 'card.settle',
            idempotencyKey: "card:settle:{$auth->network_auth_id}",
            lines: $lines,
            memo: "Card settlement {$auth->merchant}",
            metadata: [
                'authorization_id' => $auth->id,
                'fx' => [
                    'from' => $asset->symbol,
                    'to' => $fiat->symbol,
                    'crypto_settled' => $toDealer->baseString(),
                    'fiat_settled' => $fiatOwed->baseString(),
                ],
            ],
        ));
    }
}
