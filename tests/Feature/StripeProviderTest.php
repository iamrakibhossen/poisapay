<?php

declare(strict_types=1);

use App\Card\Enums\CardProviderDriver;
use App\Card\Enums\ProviderCapability;
use App\Card\Enums\WebhookEventType;
use App\Card\Factory\CardProviderFactory;
use App\Card\Providers\Stripe\StripeProvider;
use App\Enums\CardNetwork;
use App\Enums\CardStatus;
use App\Enums\CardType;
use Illuminate\Support\Facades\Config;

const CARD_WHSEC = 'whsec_card_test';

beforeEach(function () {
    Config::set('card.providers.stripe.webhook_secret', CARD_WHSEC);
});

function cardStripe(): StripeProvider
{
    /** @var StripeProvider $p */
    $p = app(CardProviderFactory::class)->driver('stripe');

    return $p;
}

function cardStripeSig(string $payload, int $ts): string
{
    return 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$payload, CARD_WHSEC);
}

it('resolves the stripe driver from config exactly like marqeta', function () {
    $provider = app(CardProviderFactory::class)->driver('stripe');

    expect($provider)->toBeInstanceOf(StripeProvider::class)
        ->and($provider->key())->toBe('stripe')
        ->and($provider->supports(ProviderCapability::VirtualCards))->toBeTrue()
        ->and($provider->supports(ProviderCapability::Freeze))->toBeTrue()
        ->and($provider->supports(ProviderCapability::RevealPan))->toBeTrue()
        ->and($provider->supports(ProviderCapability::Webhooks))->toBeTrue()
        ->and($provider->supportsJitFunding())->toBeTrue();

    expect(CardProviderDriver::configured())->toContain(CardProviderDriver::Stripe);
});

it('verifies a stripe webhook signature', function () {
    $payload = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'issuing_card.updated',
        'created' => time(), 'data' => ['object' => ['id' => 'ic_1']]]);

    expect(cardStripe()->verifyWebhook($payload, ['Stripe-Signature' => cardStripeSig($payload, time())]))->toBeTrue()
        ->and(cardStripe()->verifyWebhook($payload, ['Stripe-Signature' => 't='.time().',v1=bad']))->toBeFalse()
        ->and(cardStripe()->verifyWebhook($payload, []))->toBeFalse();
});

it('normalises a stripe authorization request into the canonical shape', function () {
    $payload = json_encode([
        'id' => 'evt_auth', 'object' => 'event', 'type' => 'issuing_authorization.request', 'created' => time(),
        'data' => ['object' => ['id' => 'iauth_1', 'object' => 'issuing.authorization',
            'pending_request' => ['amount' => 1500, 'currency' => 'usd'], 'card' => ['id' => 'ic_1']]],
    ]);

    $events = cardStripe()->processWebhook($payload, ['Stripe-Signature' => cardStripeSig($payload, time())]);

    expect($events)->toHaveCount(1);
    $e = $events[0];
    expect($e->type)->toBe(WebhookEventType::AuthorizationRequest)
        ->and($e->providerEventId)->toBe('evt_auth')
        ->and($e->providerCardRef)->toBe('ic_1')
        ->and($e->providerTxRef)->toBe('iauth_1')
        ->and($e->amountMinor)->toBe('1500')
        ->and($e->currency)->toBe('USD');
});

it('drops an unverifiable webhook', function () {
    expect(cardStripe()->processWebhook('{"id":"evt"}', ['Stripe-Signature' => 't=1,v1=bad']))->toBe([]);
});

it('maps a stripe issuing card onto the neutral CardData shape', function () {
    $map = new ReflectionMethod(StripeProvider::class, 'mapCard');
    $map->setAccessible(true);
    $provider = cardStripe();

    $card = $map->invoke($provider, [
        'id' => 'ic_1', 'type' => 'virtual', 'brand' => 'Visa', 'status' => 'active',
        'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030, 'cardholder' => ['id' => 'ich_1'],
        'number' => '4242424242424242', 'cvc' => '123',
    ], true);

    expect($card->providerCardRef)->toBe('ic_1')
        ->and($card->type)->toBe(CardType::Virtual)
        ->and($card->network)->toBe(CardNetwork::Visa)
        ->and($card->status)->toBe(CardStatus::Active)
        ->and($card->last4)->toBe('4242')
        ->and($card->expMonth)->toBe(12)
        ->and($card->expYear)->toBe(2030)
        ->and($card->cardholderRef)->toBe('ich_1')
        ->and($card->pan)->toBe('4242424242424242')
        ->and($card->cvv)->toBe('123');

    // status mapping: inactive → Frozen, canceled → Closed
    expect($map->invoke($provider, ['id' => 'ic_2', 'status' => 'inactive'], false)->status)->toBe(CardStatus::Frozen)
        ->and($map->invoke($provider, ['id' => 'ic_3', 'status' => 'canceled'], false)->status)->toBe(CardStatus::Closed);
});

it('does not leak pan/cvv unless a reveal is requested', function () {
    $map = new ReflectionMethod(StripeProvider::class, 'mapCard');
    $map->setAccessible(true);

    $card = $map->invoke(cardStripe(), ['id' => 'ic_9', 'status' => 'active', 'number' => 'x', 'cvc' => 'y'], false);

    expect($card->pan)->toBeNull()->and($card->cvv)->toBeNull();
});

it('supports real-time JIT authorization and parses the request', function () {
    $provider = cardStripe();
    expect($provider->supportsJitFunding())->toBeTrue();

    $req = $provider->parseFundingRequest(json_encode(['type' => 'issuing_authorization.request',
        'data' => ['object' => ['id' => 'iauth_x', 'card' => ['id' => 'ic_1'],
            'pending_request' => ['amount' => 2500, 'currency' => 'usd'],
            'merchant_data' => ['name' => 'Coffee', 'category_code' => '5814', 'country' => 'US']]]]), []);

    expect($req->cardRef)->toBe('ic_1')
        ->and($req->networkAuthId)->toBe('iauth_x')
        ->and($req->amountMinor)->toBe('2500')
        ->and($req->currency)->toBe('USD')
        ->and($req->mcc)->toBe('5814')
        ->and($req->merchant)->toBe('Coffee');
});

it('formats approve/decline responses (200-ack) for the JIT endpoint', function () {
    // No authorization id in context → no Stripe API call → pure formatting.
    $approve = cardStripe()->formatFundingResponse(App\Domain\Card\AuthorizationResult::approve(
        new App\Models\CardAuthorization(['network_auth_id' => 'n1'])
    ));
    $decline = cardStripe()->formatFundingResponse(App\Domain\Card\AuthorizationResult::decline('insufficient_funds'));

    expect($approve['status'])->toBe(200)->and($approve['body']['approved'])->toBeTrue()
        ->and($decline['status'])->toBe(200)->and($decline['body']['approved'])->toBeFalse()
        ->and($decline['body']['decline_reason'])->toBe('insufficient_funds');
});

it('flags the real-time request for synchronous handling', function () {
    $payload = json_encode(['id' => 'evt_req', 'object' => 'event', 'type' => 'issuing_authorization.request',
        'created' => time(), 'data' => ['object' => ['id' => 'iauth_1', 'card' => ['id' => 'ic_1']]]]);

    $events = cardStripe()->processWebhook($payload, ['Stripe-Signature' => cardStripeSig($payload, time())]);

    expect($events[0]->type)->toBe(WebhookEventType::AuthorizationRequest);
});

it('ignores the informational authorization.created event (hold placed synchronously)', function () {
    $payload = json_encode(['id' => 'evt_created', 'object' => 'event', 'type' => 'issuing_authorization.created',
        'created' => time(), 'data' => ['object' => ['id' => 'iauth_1', 'card' => ['id' => 'ic_1'], 'amount' => 2500]]]);

    $events = cardStripe()->processWebhook($payload, ['Stripe-Signature' => cardStripeSig($payload, time())]);

    expect($events[0]->type)->toBe(WebhookEventType::Unknown);
});

it('maps a settlement transaction to the original authorization for hold matching', function () {
    $payload = json_encode(['id' => 'evt_txn', 'object' => 'event', 'type' => 'issuing_transaction.created',
        'created' => time(), 'data' => ['object' => ['id' => 'ipi_1', 'type' => 'capture',
            'authorization' => 'iauth_1', 'card' => ['id' => 'ic_1'], 'amount' => -2500]]]);

    $events = cardStripe()->processWebhook($payload, ['Stripe-Signature' => cardStripeSig($payload, time())]);

    expect($events[0]->type)->toBe(WebhookEventType::TransactionCleared)
        ->and($events[0]->providerTxRef)->toBe('iauth_1'); // the auth id, not the transaction id
});

it('routes an inbound stripe webhook through the existing card pipeline', function () {
    $payload = json_encode(['id' => 'evt_pipe', 'object' => 'event', 'type' => 'issuing_card.updated',
        'created' => time(), 'data' => ['object' => ['id' => 'ic_9', 'object' => 'issuing.card']]]);

    $this->call('POST', '/api/card/webhooks/stripe', [], [], [],
        ['HTTP_STRIPE_SIGNATURE' => cardStripeSig($payload, time()), 'CONTENT_TYPE' => 'application/json'], $payload)
        ->assertOk();

    $this->assertDatabaseHas('card_webhooks', ['driver' => 'stripe', 'provider_event_id' => 'evt_pipe']);
});
