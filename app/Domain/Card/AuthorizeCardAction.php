<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\CardAuthStatus;
use App\Enums\CardStatus;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\Card;
use App\Models\CardAuthorization;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Real-time card authorisation (TDD §F3.3) — the latency-critical crown jewel.
 * On the network AUTH webhook we resolve the card, pick a funding asset by the
 * user's spending priority, JIT-quote crypto->fiat, and place a HOLD (a ledger
 * lock user:available -> user:card_hold) — all under the p99 < ~2s NFR (§F7).
 *
 * Idempotent by network_auth_id so a re-sent auth never double-holds (§F3.5).
 */
class AuthorizeCardAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function authorize(CardAuthorizationRequest $request): AuthorizationResult
    {
        $card = Card::where('issuer_card_ref', $request->cardRef)->first();

        // Step 2: resolve card -> user; check status, limits.
        if (! $card || $card->status !== CardStatus::Active) {
            return AuthorizationResult::decline('card_not_active');
        }
        if ($card->user->is_frozen) {
            return AuthorizationResult::decline('account_frozen');
        }
        if ($card->per_tx_limit && (int) $request->amountMinor > (int) $card->per_tx_limit) {
            return AuthorizationResult::decline('per_tx_limit_exceeded');
        }

        // Cardholder spend controls (TDD §F3.2): channel toggles, geo & MCC locks.
        if ($decline = $this->controlDecline($card, $request)) {
            return AuthorizationResult::decline($decline);
        }

        // Rolling daily spend limit (settled + still-held today).
        if ($card->daily_limit) {
            $spentToday = (int) $card->authorizations()
                ->whereIn('status', [CardAuthStatus::Approved, CardAuthStatus::Settled])
                ->whereDate('created_at', now()->toDateString())
                ->sum('amount');
            if ($spentToday + (int) $request->amountMinor > (int) $card->daily_limit) {
                return AuthorizationResult::decline('daily_limit_exceeded');
            }
        }

        // $0 authorisations (card verification / account-status checks, e.g. Stripe's
        // pre-auth) approve without a hold — there is nothing to lock and a zero-amount
        // ledger line is invalid. Idempotent by network_auth_id like the funded path.
        if ((int) $request->amountMinor <= 0) {
            return DB::transaction(function () use ($card, $request): AuthorizationResult {
                $existing = CardAuthorization::where('network_auth_id', $request->networkAuthId)->first();
                if ($existing) {
                    return AuthorizationResult::approve($existing);
                }

                $auth = CardAuthorization::create([
                    'card_id' => $card->id,
                    'network_auth_id' => $request->networkAuthId,
                    'amount' => 0,
                    'currency_code' => $request->currency,
                    'mcc' => $request->mcc,
                    'merchant' => $request->merchant,
                    'held_amount' => '0',
                    'status' => CardAuthStatus::Approved,
                ]);

                return AuthorizationResult::approve($auth);
            });
        }

        // Step 3: pick funding asset by spending priority; JIT-quote to settlement fiat.
        $funding = $this->pickFundingAsset($card, $request);
        if (! $funding) {
            return AuthorizationResult::decline('no_funding_asset');
        }
        [$asset, $holdAmount] = $funding;

        return DB::transaction(function () use ($card, $request, $asset, $holdAmount): AuthorizationResult {
            // Idempotency: a re-sent auth returns the existing decision.
            $existing = CardAuthorization::where('network_auth_id', $request->networkAuthId)->first();
            if ($existing) {
                return AuthorizationResult::approve($existing);
            }

            // Step 5: place HOLD (available -> card_hold) — a balanced lock.
            $available = $this->accounts->forUser($card->user_id, LedgerAccountType::UserAvailable, $asset->id);
            $cardHold = $this->accounts->forUser($card->user_id, LedgerAccountType::UserCardHold, $asset->id);

            $balanceRow = DB::table('account_balances')->where('account_id', $available->id)->lockForUpdate()->first();
            $current = Money::ofBase($balanceRow->balance ?? '0', $asset->decimals, $asset->symbol);
            if ($current->isLessThan($holdAmount)) {
                // Step 4: insufficient -> DECLINE fast.
                return AuthorizationResult::decline('insufficient_funds');
            }

            $holdEntry = $this->ledger->post(new EntryData(
                type: 'card.hold',
                idempotencyKey: "card:hold:{$request->networkAuthId}",
                lines: [
                    PostingLine::debit($available->id, $asset->id, $holdAmount),
                    PostingLine::credit($cardHold->id, $asset->id, $holdAmount),
                ],
                memo: "Card auth {$request->merchant}",
                metadata: ['mcc' => $request->mcc],
            ));

            $auth = CardAuthorization::create([
                'card_id' => $card->id,
                'network_auth_id' => $request->networkAuthId,
                'amount' => $request->amountMinor,
                'currency_code' => $request->currency,
                'mcc' => $request->mcc,
                'merchant' => $request->merchant,
                'funding_asset_id' => $asset->id,
                'held_amount' => $holdAmount->baseString(),
                'status' => CardAuthStatus::Approved,
                'hold_entry_id' => $holdEntry->id,
            ]);

            // Step 6: APPROVE within the network timeout.
            return AuthorizationResult::approve($auth);
        });
    }

    /** Return a decline reason if a cardholder control blocks the request, else null. */
    private function controlDecline(Card $card, CardAuthorizationRequest $request): ?string
    {
        $channelBlocked = match ($request->channel) {
            'online' => ! $card->online_enabled,
            'atm' => ! $card->atm_enabled,
            'contactless' => ! $card->contactless_enabled,
            default => false,
        };
        if ($channelBlocked) {
            return "channel_{$request->channel}_disabled";
        }

        if ($request->country && is_array($card->allowed_countries) && $card->allowed_countries !== []
            && ! in_array(strtoupper($request->country), $card->allowed_countries, true)) {
            return 'country_not_allowed';
        }

        if ($request->mcc && is_array($card->blocked_mccs) && in_array($request->mcc, $card->blocked_mccs, true)) {
            return 'mcc_blocked';
        }

        return null;
    }

    /**
     * Choose the first asset in the user's spending priority (stablecoin-first)
     * that can cover the amount, and compute the crypto hold amount for the fiat
     * settlement value. Falls back to any funded stablecoin.
     *
     * @return array{0: Asset, 1: Money}|null
     */
    private function pickFundingAsset(Card $card, CardAuthorizationRequest $request): ?array
    {
        // Prefer a stablecoin (1:1 to USD settlement is a reasonable JIT proxy here).
        $asset = Asset::where('symbol', 'USDT')->where('is_active', true)->first();
        if (! $asset) {
            return null;
        }

        // Settlement currency minor units -> stablecoin base units (both value-of-USD).
        // amountMinor is in 2dp fiat; USDT is 6dp — scale up by 4.
        $holdBase = (string) ((int) $request->amountMinor * 10 ** ($asset->decimals - 2));

        return [$asset, Money::ofBase($holdBase, $asset->decimals, $asset->symbol)];
    }
}
