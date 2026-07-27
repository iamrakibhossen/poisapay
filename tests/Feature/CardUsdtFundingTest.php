<?php

declare(strict_types=1);

use App\Domain\Card\AuthorizeCardAction;
use App\Domain\Card\CardAuthorizationRequest;
use App\Domain\Card\SettleCardAuthAction;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Enums\CardAuthStatus;
use App\Enums\CardStatus;
use App\Enums\LedgerAccountType;
use App\Models\Card;
use App\Models\CardAuthorization;
use App\Models\User;

/**
 * Card spending: same-fiat first, then an automatic USDT→fiat conversion during
 * authorisation + settlement (never a user-initiated exchange). Merchant is always
 * settled in the requested fiat; if nothing covers, "Insufficient balance.".
 */
beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->gbp = fiatAsset('GBP');
    $this->ledger = app(LedgerService::class);
    $this->accounts = app(AccountResolver::class);
    $this->user = User::factory()->create();
    $this->card = Card::create([
        'user_id' => $this->user->id,
        'program' => 'poisapay-demo',
        'type' => 'virtual',
        'network' => 'visa',
        'issuer_card_ref' => 'tok_'.str_repeat('b', 24),
        'last4' => '4242',
        'status' => CardStatus::Active,
        'settlement_currency' => 'GBP',
    ]);
});

function authorizeGbp(string $authId, string $minor): CardAuthorization
{
    app(AuthorizeCardAction::class)->authorize(new CardAuthorizationRequest(
        cardRef: test()->card->issuer_card_ref, networkAuthId: $authId, amountMinor: $minor, currency: 'GBP', merchant: 'Amazon',
    ));

    return CardAuthorization::where('network_auth_id', $authId)->firstOrFail();
}

it('converts USDT to the merchant fiat at settlement and settles in fiat', function () {
    updateSetting('card_fee_bps', 0); // isolate the FX legs
    creditUser($this->user, $this->usdt, '100000000'); // 100 USDT

    // £25 → 32 USDT held (1.28 USDT/GBP, rounded up).
    $auth = authorizeGbp('auth_fx', '2500');
    expect($auth->funding_asset_id)->toBe($this->usdt->id)
        ->and($auth->held_amount)->toBe('32000000');

    app(SettleCardAuthAction::class)->execute($auth);

    $settlementGbp = $this->accounts->system(LedgerAccountType::CardProgramSettlement, $this->gbp->id)->fresh('balance')->money();
    $dealerUsdt = $this->accounts->system(LedgerAccountType::TradingInventory, $this->usdt->id)->fresh('balance')->money();
    $dealerGbp = $this->accounts->system(LedgerAccountType::TradingInventory, $this->gbp->id)->fresh('balance')->money();

    expect($auth->fresh()->status)->toBe(CardAuthStatus::Settled)
        // Merchant settled in GBP: £25.00.
        ->and($settlementGbp->baseString())->toBe('2500')
        // House took the converted USDT into its dealer position, and is short the GBP it sourced.
        ->and($dealerUsdt->baseString())->toBe('32000000')
        ->and($dealerGbp->baseString())->toBe('-2500')
        // The user's USDT hold is fully cleared.
        ->and($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('68000000');
});

it('books the card fee in the funding crypto on a converted settlement', function () {
    updateSetting('card_fee_bps', 100); // 1%
    creditUser($this->user, $this->usdt, '100000000');

    $auth = authorizeGbp('auth_fx_fee', '2500'); // 32 USDT held
    app(SettleCardAuthAction::class)->execute($auth);

    $feeCard = $this->accounts->system(LedgerAccountType::FeeCard, $this->usdt->id)->fresh('balance')->money();
    $settlementGbp = $this->accounts->system(LedgerAccountType::CardProgramSettlement, $this->gbp->id)->fresh('balance')->money();

    // Fee = 1% of 32 USDT = 0.32 USDT; merchant still settled the full £25.
    expect($feeCard->baseString())->toBe('320000')
        ->and($settlementGbp->baseString())->toBe('2500');
});

it('exposes the funding source and exchange rate for history', function () {
    creditUser($this->user, $this->usdt, '100000000');
    $auth = authorizeGbp('auth_hist', '2500');

    expect($auth->fundingSourceLabel())->toBe('32 USDT')
        ->and($auth->exchangeRateLabel())->toBe('1 USDT = 0.7813 GBP'); // £25 / 32 USDT
});

it('labels a fiat-funded charge as a wallet spend with no rate', function () {
    creditUser($this->user, $this->gbp, '5000'); // £50
    $auth = authorizeGbp('auth_fiat', '2500');

    expect($auth->funding_asset_id)->toBe($this->gbp->id)
        ->and($auth->fundedByConversion())->toBeFalse()
        ->and($auth->fundingSourceLabel())->toBe('GBP Wallet')
        ->and($auth->exchangeRateLabel())->toBeNull();
});

it('declines with "Insufficient balance." when neither fiat nor USDT can cover it', function () {
    creditUser($this->user, $this->usdt, '1000000'); // 1 USDT — nowhere near £25

    $result = app(AuthorizeCardAction::class)->authorize(new CardAuthorizationRequest(
        cardRef: $this->card->issuer_card_ref, networkAuthId: 'auth_broke', amountMinor: '2500', currency: 'GBP', merchant: 'Amazon',
    ));

    expect($result->approved)->toBeFalse()
        ->and($result->reason)->toBe('insufficient_funds')
        ->and($result->message())->toBe('Insufficient balance.');
});

it('settles a fiat-funded charge directly in place', function () {
    creditUser($this->user, $this->gbp, '5000'); // £50
    $auth = authorizeGbp('auth_fiat_settle', '2500');

    app(SettleCardAuthAction::class)->execute($auth);

    $settlementGbp = $this->accounts->system(LedgerAccountType::CardProgramSettlement, $this->gbp->id)->fresh('balance')->money();
    expect($auth->fresh()->status)->toBe(CardAuthStatus::Settled)
        ->and($settlementGbp->isPositive())->toBeTrue();
});
