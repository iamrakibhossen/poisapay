<?php

declare(strict_types=1);

use App\Domain\Card\AuthorizeCardAction;
use App\Domain\Card\CardAuthorizationRequest;
use App\Domain\Ledger\LedgerService;
use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardAuthorization;
use App\Models\User;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create();
    $this->card = Card::create([
        'user_id' => $this->user->id,
        'program' => 'poisapay-demo',
        'type' => 'virtual',
        'network' => 'visa',
        'issuer_card_ref' => 'tok_'.str_repeat('a', 24),
        'last4' => '4242',
        'status' => CardStatus::Active,
        'settlement_currency' => 'USD',
    ]);
});

it('places a hold on authorisation and moves available -> card_hold', function () {
    creditUser($this->user, $this->usdt, '100000000'); // 100 USDT

    $result = app(AuthorizeCardAction::class)->authorize(new CardAuthorizationRequest(
        cardRef: $this->card->issuer_card_ref,
        networkAuthId: 'auth_001',
        amountMinor: '2500', // $25.00
        currency: 'USD',
        merchant: 'Coffee Shop',
    ));

    expect($result->approved)->toBeTrue()
        ->and($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('75000000')
        ->and($this->ledger->lockedBalance($this->user, $this->usdt->id)->baseString())->toBe('0'); // held, not "locked"
});

it('is idempotent — a re-sent auth never double-holds', function () {
    creditUser($this->user, $this->usdt, '100000000');
    $action = app(AuthorizeCardAction::class);

    // $10.00 settlement => 10 USDT held (6dp) => 10_000_000 base units.
    $req = new CardAuthorizationRequest($this->card->issuer_card_ref, 'auth_dup', '1000', 'USD', merchant: 'X');
    $action->authorize($req);
    $action->authorize($req); // replay

    expect(CardAuthorization::where('network_auth_id', 'auth_dup')->count())->toBe(1)
        ->and($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('90000000');
});

it('declines when funds are insufficient', function () {
    creditUser($this->user, $this->usdt, '1000000'); // 1 USDT

    $result = app(AuthorizeCardAction::class)->authorize(new CardAuthorizationRequest(
        cardRef: $this->card->issuer_card_ref,
        networkAuthId: 'auth_poor',
        amountMinor: '5000', // $50
        currency: 'USD',
    ));

    expect($result->approved)->toBeFalse()
        ->and($result->reason)->toBe('insufficient_funds');
});

it('declines a frozen card', function () {
    $this->card->update(['status' => CardStatus::Frozen]);

    $result = app(AuthorizeCardAction::class)->authorize(new CardAuthorizationRequest(
        cardRef: $this->card->issuer_card_ref,
        networkAuthId: 'auth_frozen',
        amountMinor: '100',
        currency: 'USD',
    ));

    expect($result->approved)->toBeFalse();
});
