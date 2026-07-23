<?php

declare(strict_types=1);

use App\Card\CardManager;
use App\Domain\Card\GenerateCardAction;
use App\Enums\CardAuthStatus;
use App\Enums\CardStatus;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardAuthorization;
use App\Models\CardProvider;
use App\Models\CardWebhook;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function mockProvider(): CardProvider
{
    return CardProvider::firstOrCreate(['slug' => 'mock-issuer'], [
        'name' => 'Mock Issuer', 'driver' => 'mock', 'network' => 'visa',
        'supports_virtual' => true, 'supports_physical' => false, 'settlement_currency' => 'USD', 'is_active' => true,
    ]);
}

function activeCard(User $user, CardProvider $provider): Card
{
    $card = app(GenerateCardAction::class)->execute($user, $provider, CardType::Virtual);
    $card->update(['status' => CardStatus::Active]);

    return $card->refresh();
}

/** Sign a body with the mock provider and POST it as a raw request. */
function postSigned(string $uri, array $body): TestResponse
{
    $raw = json_encode($body);
    $sig = app(CardManager::class)->driver('mock')->sign($raw);

    return test()->call('POST', $uri, [], [], [], ['HTTP_X_MOCK_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $raw);
}

beforeEach(function () {
    $this->asset = testAsset('USDT');
    $this->user = User::factory()->create();
    creditUser($this->user, $this->asset, '20000000'); // 20 USDT
    $this->card = activeCard($this->user, mockProvider());
});

it('approves a JIT funding request against the ledger and places a hold', function () {
    $res = postSigned('/api/card/jit/mock', [
        'card_ref' => $this->card->issuer_card_ref,
        'network_auth_id' => 'auth_1',
        'amount_minor' => '1000',
        'currency' => 'USD',
        'merchant' => 'Coffee',
    ]);

    $res->assertStatus(200)->assertJson(['decision' => 'approve']);

    $auth = CardAuthorization::where('network_auth_id', 'auth_1')->first();
    expect($auth)->not->toBeNull()
        ->and($auth->status)->toBe(CardAuthStatus::Approved);
});

it('declines a JIT request when the wallet cannot cover it', function () {
    $broke = User::factory()->create();
    $card = activeCard($broke, mockProvider());

    $res = postSigned('/api/card/jit/mock', [
        'card_ref' => $card->issuer_card_ref,
        'network_auth_id' => 'auth_broke',
        'amount_minor' => '1000',
        'currency' => 'USD',
    ]);

    $res->assertStatus(402)->assertJson(['decision' => 'decline', 'reason' => 'insufficient_funds']);
});

it('rejects a JIT request with a bad signature', function () {
    $raw = json_encode(['card_ref' => $this->card->issuer_card_ref, 'network_auth_id' => 'x', 'amount_minor' => '100', 'currency' => 'USD']);

    $this->call('POST', '/api/card/jit/mock', [], [], [], ['HTTP_X_MOCK_SIGNATURE' => 'wrong', 'CONTENT_TYPE' => 'application/json'], $raw)
        ->assertStatus(401);
});

it('settles an approved auth when a clearing webhook arrives', function () {
    postSigned('/api/card/jit/mock', [
        'card_ref' => $this->card->issuer_card_ref,
        'network_auth_id' => 'auth_2',
        'amount_minor' => '1000',
        'currency' => 'USD',
    ])->assertStatus(200);

    postSigned('/api/card/webhooks/mock', [
        'id' => 'evt_clear_1',
        'type' => 'transaction.cleared',
        'card_ref' => $this->card->issuer_card_ref,
        'tx_ref' => 'auth_2',
        'amount_minor' => '1000',
        'currency' => 'USD',
    ])->assertStatus(200)->assertJson(['received' => 1]);

    expect(CardAuthorization::where('network_auth_id', 'auth_2')->first()->status)
        ->toBe(CardAuthStatus::Settled);
    expect(CardWebhook::where('provider_event_id', 'evt_clear_1')->first()->status)->toBe('processed');
});

it('deduplicates a repeated webhook event', function () {
    $body = ['id' => 'evt_dupe', 'type' => 'transaction.cleared', 'tx_ref' => 'none', 'currency' => 'USD'];

    postSigned('/api/card/webhooks/mock', $body)->assertJson(['received' => 1]);
    postSigned('/api/card/webhooks/mock', $body)->assertJson(['received' => 0]);

    expect(CardWebhook::where('provider_event_id', 'evt_dupe')->count())->toBe(1);
});

it('rejects a webhook with a bad signature', function () {
    $raw = json_encode(['id' => 'e', 'type' => 'transaction.cleared']);

    $this->call('POST', '/api/card/webhooks/mock', [], [], [], ['HTTP_X_MOCK_SIGNATURE' => 'nope', 'CONTENT_TYPE' => 'application/json'], $raw)
        ->assertStatus(401);
});
