<?php

declare(strict_types=1);

use App\Domain\Card\AuthorizeCardAction;
use App\Domain\Card\CardAuthorizationRequest;
use App\Domain\Card\CloseCardAction;
use App\Domain\Card\OpenCardDisputeAction;
use App\Domain\Card\RefundCardAuthAction;
use App\Domain\Card\ResolveCardDisputeAction;
use App\Domain\Card\SetCardPinAction;
use App\Domain\Card\SettleCardAuthAction;
use App\Domain\Card\UpdateCardControlsAction;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Enums\CardAuthStatus;
use App\Enums\CardStatus;
use App\Enums\LedgerAccountType;
use App\Models\Card;
use App\Models\CardAuthorization;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->resolver = app(AccountResolver::class);
    $this->user = User::factory()->create();
    $this->card = Card::create([
        'user_id' => $this->user->id,
        'program' => 'poisapay-demo',
        'type' => 'virtual',
        'network' => 'visa',
        'issuer_card_ref' => 'tok_'.str_repeat('b', 24),
        'last4' => '4242',
        'status' => CardStatus::Active,
        'settlement_currency' => 'USD',
    ]);
    creditUser($this->user, $this->usdt, '100000000'); // 100 USDT
});

/** Read a system account's raw signed balance (base units). */
function systemBalance(LedgerAccountType $type, int $assetId): string
{
    $account = app(AccountResolver::class)->system($type, $assetId);

    return (string) (DB::table('account_balances')->where('account_id', $account->id)->value('balance') ?? '0');
}

function authorizeFixture(Card $card, string $authId, string $amountMinor): CardAuthorization
{
    app(AuthorizeCardAction::class)->authorize(new CardAuthorizationRequest(
        cardRef: $card->issuer_card_ref,
        networkAuthId: $authId,
        amountMinor: $amountMinor,
        currency: 'USD',
        merchant: 'Test Merchant',
        mcc: '5411',
    ));

    return CardAuthorization::where('network_auth_id', $authId)->firstOrFail();
}

it('settles a full hold: releases card_hold, credits treasury net of the card fee', function () {
    $auth = authorizeFixture($this->card, 'auth_settle', '2500'); // $25 => 25 USDT held

    app(SettleCardAuthAction::class)->execute($auth);

    // 1% card fee: 25 -> 24.75 to the card program settlement, 0.25 fee.
    expect($auth->refresh()->status)->toBe(CardAuthStatus::Settled)
        ->and(systemBalance(LedgerAccountType::CardProgramSettlement, $this->usdt->id))->toBe('24750000')
        ->and(systemBalance(LedgerAccountType::FeeCard, $this->usdt->id))->toBe('250000')
        ->and($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('75000000');
});

it('is idempotent — re-settling the same auth does not move money twice', function () {
    $auth = authorizeFixture($this->card, 'auth_idem', '2500');
    $settle = app(SettleCardAuthAction::class);

    $settle->execute($auth);
    $settle->execute($auth->refresh());

    expect(systemBalance(LedgerAccountType::FeeCard, $this->usdt->id))->toBe('250000');
});

it('releases the over-hold to the user when settlement is less than the hold', function () {
    $auth = authorizeFixture($this->card, 'auth_partial', '2500'); // 25 USDT held

    app(SettleCardAuthAction::class)->execute($auth, Money::ofBase('10000000', 6, 'USDT')); // settle 10

    // 15 released back to available (75 + 15 = 90), fee 1% of 10 = 0.1.
    expect($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('90000000')
        ->and(systemBalance(LedgerAccountType::FeeCard, $this->usdt->id))->toBe('100000');
});

it('refunds a settled purchase back to the cardholder and marks it reversed', function () {
    $auth = authorizeFixture($this->card, 'auth_refund', '2500');
    app(SettleCardAuthAction::class)->execute($auth);

    app(RefundCardAuthAction::class)->execute($auth->refresh());

    // Full 25 USDT returned: available back to 100.
    expect($auth->refresh()->status)->toBe(CardAuthStatus::Reversed)
        ->and($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('100000000');
});

it('resolves a lost dispute as a chargeback that reimburses the cardholder', function () {
    $auth = authorizeFixture($this->card, 'auth_dispute', '2500');
    app(SettleCardAuthAction::class)->execute($auth);

    $dispute = app(OpenCardDisputeAction::class)->execute($auth->refresh(), 'fraud');
    app(ResolveCardDisputeAction::class)->execute($dispute, 'lost');

    // Chargeback returns 25 USDT to the user (75 + 25 = 100) and books the loss.
    expect($dispute->refresh()->status)->toBe('lost')
        ->and($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('100000000')
        ->and(systemBalance(LedgerAccountType::CardProgramLoss, $this->usdt->id))->toBe('25000000');
});

it('does not move money when a dispute is won', function () {
    $auth = authorizeFixture($this->card, 'auth_won', '2500');
    app(SettleCardAuthAction::class)->execute($auth);
    $before = $this->ledger->availableBalance($this->user, $this->usdt->id)->baseString();

    $dispute = app(OpenCardDisputeAction::class)->execute($auth->refresh(), 'fraud');
    app(ResolveCardDisputeAction::class)->execute($dispute, 'won');

    expect($dispute->refresh()->status)->toBe('won')
        ->and($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe($before);
});

it('enforces cardholder spend controls at authorisation', function () {
    // Block the ATM channel and the grocery MCC; lock to US only.
    app(UpdateCardControlsAction::class)->execute($this->card, [
        'online_enabled' => false,
        'blocked_mccs' => '5411',
        'allowed_countries' => 'us,gb',
    ]);
    $this->card->refresh();

    expect($this->card->allowed_countries)->toBe(['US', 'GB'])
        ->and($this->card->blocked_mccs)->toBe(['5411']);

    // Online disabled -> declined.
    $online = app(AuthorizeCardAction::class)->authorize(new CardAuthorizationRequest(
        $this->card->issuer_card_ref, 'auth_ctrl_1', '500', 'USD', mcc: '5812', channel: 'online', country: 'US'));
    expect($online->approved)->toBeFalse()->and($online->reason)->toBe('channel_online_disabled');

    // Contactless in a blocked MCC -> declined.
    $mcc = app(AuthorizeCardAction::class)->authorize(new CardAuthorizationRequest(
        $this->card->issuer_card_ref, 'auth_ctrl_2', '500', 'USD', mcc: '5411', channel: 'contactless', country: 'US'));
    expect($mcc->approved)->toBeFalse()->and($mcc->reason)->toBe('mcc_blocked');

    // Contactless, allowed MCC, disallowed country -> declined.
    $geo = app(AuthorizeCardAction::class)->authorize(new CardAuthorizationRequest(
        $this->card->issuer_card_ref, 'auth_ctrl_3', '500', 'USD', mcc: '5812', channel: 'contactless', country: 'FR'));
    expect($geo->approved)->toBeFalse()->and($geo->reason)->toBe('country_not_allowed');
});

it('hashes a PIN one-way and never stores it in the clear', function () {
    app(SetCardPinAction::class)->execute($this->card, '1234');
    $this->card->refresh();

    expect($this->card->hasPin())->toBeTrue()
        ->and($this->card->pin_hash)->not->toBe('1234')
        ->and(Hash::check('1234', $this->card->pin_hash))->toBeTrue();
});

it('rejects an invalid PIN', function () {
    app(SetCardPinAction::class)->execute($this->card, '12'); // too short
})->throws(RuntimeException::class);

it('refuses to close a card that still has a pending hold', function () {
    authorizeFixture($this->card, 'auth_open_hold', '2500'); // approved, not settled

    app(CloseCardAction::class)->execute($this->card);
})->throws(RuntimeException::class);

it('closes a card with no pending holds', function () {
    app(CloseCardAction::class)->execute($this->card);

    expect($this->card->refresh()->status)->toBe(CardStatus::Closed)
        ->and($this->card->closed_at)->not->toBeNull();
});
