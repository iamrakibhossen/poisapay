<?php

declare(strict_types=1);

use App\Card\CardManager;
use App\Card\Contracts\CardProviderInterface;
use App\Card\DTOs\CardholderData;
use App\Card\DTOs\CardIssueRequest;
use App\Card\Enums\WebhookEventType;
use App\Domain\Card\AuthorizationResult;
use App\Enums\CardStatus;
use App\Enums\CardType;
use App\Models\CardAuthorization;
use Illuminate\Support\Facades\Http;

function marqeta(): CardProviderInterface
{
    config([
        'card.providers.marqeta.card_product_token' => 'prod_x',
        'card.providers.marqeta.inbound_username' => 'poisapay',
        'card.providers.marqeta.inbound_password' => 'password',
    ]);

    return app(CardManager::class)->driver('marqeta');
}

function fakeMarqeta(): void
{
    Http::fake(function ($request) {
        $url = $request->url();
        $method = $request->method();

        return match (true) {
            str_contains($url, '/cards/card_x') => Http::response([
                'token' => 'card_x', 'state' => 'ACTIVE', 'last_four' => '4321', 'expiration' => '0530',
                'user_token' => 'usr_x', 'pan' => '4000001234564321', 'cvv_number' => '123',
            ], 200),
            str_ends_with($url, '/users') && $method === 'POST' => Http::response(['token' => 'usr_x', 'status' => 'ACTIVE'], 201),
            str_ends_with($url, '/cards') && $method === 'POST' => Http::response([
                'token' => 'card_x', 'state' => 'UNACTIVATED', 'last_four' => '4321', 'expiration' => '0530', 'user_token' => 'usr_x',
            ], 201),
            str_ends_with($url, '/cardtransitions') => Http::response(['token' => 'ct_1', 'state' => 'SUSPENDED', 'card_token' => 'card_x'], 201),
            default => Http::response([], 200),
        };
    });
}

it('creates a Marqeta cardholder via POST /users', function () {
    fakeMarqeta();
    $result = marqeta()->createCardholder(new CardholderData('user-1', 'Ada', 'Lovelace', 'ada@example.com'));

    expect($result->providerRef)->toBe('usr_x');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/users') && $r['first_name'] === 'Ada');
});

it('reuses an existing cardholder when Marqeta reports a duplicate', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        if (str_ends_with($url, '/users') && $request->method() === 'POST') {
            return Http::response(['error_message' => 'A card holder with the same email already exist', 'error_code' => '400057'], 400);
        }

        return Http::response(['token' => 'user-1', 'status' => 'ACTIVE'], 200); // GET /users/user-1
    });

    $result = marqeta()->createCardholder(new CardholderData('user-1', 'Ada', 'Lovelace'));

    expect($result->providerRef)->toBe('user-1'); // recovered via GET, not a hard failure
});

it('issues a Marqeta card and maps its fields', function () {
    fakeMarqeta();
    $card = marqeta()->createVirtualCard(new CardIssueRequest('usr_x', CardType::Virtual, 'prog'));

    expect($card->providerCardRef)->toBe('card_x')
        ->and($card->last4)->toBe('4321')
        ->and($card->expMonth)->toBe(5)
        ->and($card->expYear)->toBe(2030)
        ->and($card->status)->toBe(CardStatus::Inactive);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/cards') && $r['card_product_token'] === 'prod_x');
});

it('reveals PAN/CVV only when asked', function () {
    fakeMarqeta();
    $card = marqeta()->getCard('card_x', reveal: true);

    expect($card->pan)->toBe('4000001234564321')->and($card->cvv)->toBe('123');
});

it('freezes a card via a SUSPENDED cardtransition', function () {
    fakeMarqeta();
    $card = marqeta()->freezeCard('card_x');

    expect($card->status)->toBe(CardStatus::Frozen);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/cardtransitions') && $r['state'] === 'SUSPENDED');
});

it('verifies inbound Basic auth on webhooks', function () {
    $p = marqeta(); // config inbound defaults: poisapay / password
    $good = ['authorization' => 'Basic '.base64_encode('poisapay:password')];
    $bad = ['authorization' => 'Basic '.base64_encode('poisapay:wrong')];

    expect($p->verifyWebhook('{}', $good))->toBeTrue()
        ->and($p->verifyWebhook('{}', $bad))->toBeFalse();
});

it('normalizes a clearing transaction to the original auth token', function () {
    $body = json_encode(['transactions' => [[
        'token' => 'clr_1', 'type' => 'authorization.clearing',
        'preceding_related_transaction_token' => 'auth_9',
        'amount' => 10.00, 'currency_code' => 'USD', 'card_token' => 'card_x',
    ]]]);

    $events = marqeta()->processWebhook($body, []);

    expect($events)->toHaveCount(1)
        ->and($events[0]->type)->toBe(WebhookEventType::TransactionCleared)
        ->and($events[0]->providerTxRef)->toBe('auth_9')
        ->and($events[0]->amountMinor)->toBe('1000');
});

it('parses a JIT gateway message and formats approve/decline', function () {
    $p = marqeta();
    $body = json_encode([
        'token' => 'txn_1', 'card_token' => 'card_x', 'amount' => 12.50, 'currency_code' => 'USD',
        'gpa_order' => ['jit_funding' => ['token' => 'jit_1', 'amount' => 12.50]],
        'card_acceptor' => ['mcc' => '5814', 'name' => 'Cafe'],
    ]);

    $req = $p->parseFundingRequest($body, []);
    expect($req->cardRef)->toBe('card_x')->and($req->amountMinor)->toBe('1250')->and($req->mcc)->toBe('5814');

    $approve = $p->formatFundingResponse(AuthorizationResult::approve(new CardAuthorization));
    expect($approve['status'])->toBe(200)->and($approve['body']['jit_funding']['token'])->toBe('jit_1');

    $decline = $p->formatFundingResponse(AuthorizationResult::decline('insufficient_funds'));
    expect($decline['status'])->toBe(402);
});
