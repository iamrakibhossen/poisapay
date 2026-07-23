<?php

declare(strict_types=1);

namespace App\Card\Providers\Stripe;

use App\Card\DTOs\CardData;
use App\Card\DTOs\CardholderData;
use App\Card\DTOs\CardholderResult;
use App\Card\DTOs\CardIssueRequest;
use App\Card\DTOs\NormalizedWebhookEvent;
use App\Card\DTOs\ProviderHealth;
use App\Card\DTOs\RevealSession;
use App\Card\Enums\ProviderCapability;
use App\Card\Enums\WebhookEventType;
use App\Card\Exceptions\ProviderRequestException;
use App\Card\Providers\AbstractCardProvider;
use App\Card\Support\ProviderLogger;
use App\Domain\Card\AuthorizationResult;
use App\Domain\Card\CardAuthorizationRequest;
use App\Enums\CardNetwork;
use App\Enums\CardStatus;
use App\Enums\CardType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Event;
use Stripe\StripeObject;
use Stripe\Webhook;
use Throwable;

/**
 * Stripe Issuing card-provider driver — a sibling of {@see \App\Card\Providers\Marqeta\MarqetaProvider}
 * behind the same {@see \App\Card\Contracts\CardProviderInterface}. Selected per card
 * via config/card.php exactly like Marqeta; the rest of the app never knows which
 * provider is active. All Stripe SDK code lives in this driver + {@see StripeClient}.
 */
class StripeProvider extends AbstractCardProvider
{
    private readonly StripeClient $client;

    /** @var array<string, mixed>|null Context stashed by parseFundingRequest for the JIT decision. */
    private ?array $jitContext = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly string $key,
        private readonly array $config = [],
    ) {
        $this->client = new StripeClient($config, app(ProviderLogger::class), $key);
    }

    public function key(): string
    {
        return $this->key;
    }

    /** @return list<ProviderCapability> */
    public function capabilities(): array
    {
        return [
            ProviderCapability::VirtualCards,
            ProviderCapability::PhysicalCards,
            ProviderCapability::Freeze,
            ProviderCapability::Terminate,
            ProviderCapability::Replace,
            ProviderCapability::RevealPan,
            ProviderCapability::JitFunding,
            ProviderCapability::Webhooks,
        ];
    }

    // ── Cardholders ───────────────────────────────────────────────────────────
    public function createCardholder(CardholderData $data): CardholderResult
    {
        // Stripe requires a billing address; fall back to the configured default when
        // the user has none on file.
        $billing = $this->address($data->address);
        if ($billing === []) {
            $billing = $this->address((array) ($this->config['billing_address'] ?? []));
        }

        $params = array_filter([
            'name' => trim($data->firstName.' '.$data->lastName),
            'email' => $data->email,
            'phone_number' => $data->phone,
            'status' => 'active',
            'type' => 'individual',
            'metadata' => ['external_id' => $data->externalId] + $data->metadata,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        if ($billing !== []) {
            $params['billing'] = ['address' => $billing];
        }

        // Individual cardholders must accept Stripe's Authorized User Terms, else the
        // cardholder carries outstanding requirements and its cards cannot activate.
        $params['individual'] = [
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'card_issuing' => [
                'user_terms_acceptance' => [
                    'date' => time(),
                    'ip' => request()->ip() ?: '0.0.0.0',
                ],
            ],
        ];

        $cardholder = $this->guard(fn () => $this->client->sdk()->issuing->cardholders->create(
            $params,
            // Key derived from the params so a prior failed attempt (different params)
            // never blocks a corrected retry, while identical retries stay idempotent.
            ['idempotency_key' => 'ch:'.$data->externalId.':'.substr(md5(json_encode($params)), 0, 10)],
        ));

        return new CardholderResult((string) $cardholder->id, $cardholder->status ?? null, $cardholder->toArray());
    }

    public function updateCardholder(string $cardholderRef, CardholderData $data): CardholderResult
    {
        $cardholder = $this->guard(fn () => $this->client->sdk()->issuing->cardholders->update($cardholderRef, array_filter([
            'email' => $data->email,
            'phone_number' => $data->phone,
        ])));

        return new CardholderResult($cardholderRef, $cardholder->status ?? null, $cardholder->toArray());
    }

    // ── Card lifecycle ────────────────────────────────────────────────────────
    public function createVirtualCard(CardIssueRequest $request): CardData
    {
        return $this->issue($request, 'virtual');
    }

    public function createPhysicalCard(CardIssueRequest $request): CardData
    {
        return $this->issue($request, 'physical');
    }

    private function issue(CardIssueRequest $request, string $type): CardData
    {
        $card = $this->guard(fn () => $this->client->sdk()->issuing->cards->create(array_filter([
            'cardholder' => $request->cardholderRef,
            'currency' => strtolower($request->currency),
            'type' => $type,
            'status' => 'active',
            'metadata' => array_filter(['program' => $request->program, 'nickname' => $request->nickname]) + $request->metadata,
        ])));

        return $this->mapCard($card->toArray());
    }

    public function getCard(string $providerCardRef, bool $reveal = false): CardData
    {
        $params = $reveal ? ['expand' => ['number', 'cvc']] : [];
        $card = $this->guard(fn () => $this->client->sdk()->issuing->cards->retrieve($providerCardRef, $params));

        return $this->mapCard($card->toArray(), $reveal);
    }

    /**
     * Mint a Stripe Issuing ephemeral key so the browser can render the card's PAN/CVV
     * via Stripe.js display Elements. The key is scoped to this one card + the client's
     * one-time nonce and expires in minutes; the PAN never reaches our server. Stripe
     * requires the ephemeral key be created with the API version the client SDK pins.
     */
    public function createRevealSession(string $providerCardRef, array $context = []): RevealSession
    {
        $this->requireCapability(ProviderCapability::RevealPan);

        $nonce = trim((string) ($context['nonce'] ?? ''));
        if ($nonce === '') {
            throw new ProviderRequestException('A client nonce is required to reveal card details.', 422);
        }

        $key = $this->guard(fn () => $this->client->sdk()->ephemeralKeys->create(
            ['issuing_card' => $providerCardRef, 'nonce' => $nonce],
            ['stripe_version' => $this->ephemeralApiVersion()],
        ));

        return new RevealSession(
            driver: $this->key,
            providerCardRef: $providerCardRef,
            ephemeralKeySecret: (string) $key->secret,
            expiresAt: isset($key->expires) ? (int) $key->expires : null,
        );
    }

    /** API version the ephemeral key is minted with — must match what @stripe/stripe-js expects. */
    private function ephemeralApiVersion(): string
    {
        return (string) ($this->config['ephemeral_key_api_version'] ?? $this->config['api_version'] ?? '2020-03-02');
    }

    public function listCards(string $cardholderRef): array
    {
        $cards = $this->guard(fn () => $this->client->sdk()->issuing->cards->all(['cardholder' => $cardholderRef, 'limit' => 100]));

        return array_map(fn ($c) => $this->mapCard($c->toArray()), $cards->data);
    }

    public function freezeCard(string $providerCardRef, ?string $reason = null): CardData
    {
        return $this->setStatus($providerCardRef, 'inactive');
    }

    public function unfreezeCard(string $providerCardRef): CardData
    {
        return $this->setStatus($providerCardRef, 'active');
    }

    public function terminateCard(string $providerCardRef, ?string $reason = null): CardData
    {
        // Cancellation is terminal in Stripe (cannot be undone).
        return $this->setStatus($providerCardRef, 'canceled', $reason);
    }

    public function replaceCard(string $providerCardRef, ?string $reason = null): CardData
    {
        // Stripe has no single "replace": cancel the old card and issue a new one for
        // the same cardholder + type/currency.
        $old = $this->guard(fn () => $this->client->sdk()->issuing->cards->retrieve($providerCardRef))->toArray();
        $this->setStatus($providerCardRef, 'canceled', $reason);

        $card = $this->guard(fn () => $this->client->sdk()->issuing->cards->create(array_filter([
            'cardholder' => $this->cardholderId($old),
            'currency' => $old['currency'] ?? 'usd',
            'type' => $old['type'] ?? 'virtual',
            'status' => 'active',
            'replacement_for' => $providerCardRef,
            'replacement_reason' => 'expired',
        ])));

        return $this->mapCard($card->toArray());
    }

    private function setStatus(string $providerCardRef, string $status, ?string $reason = null): CardData
    {
        $params = ['status' => $status];
        if ($status === 'canceled' && $reason !== null) {
            $params['cancellation_reason'] = 'lost'; // Stripe enum; reason detail kept in our audit log
        }

        $card = $this->guard(fn () => $this->client->sdk()->issuing->cards->update($providerCardRef, $params));

        return $this->mapCard($card->toArray());
    }

    /** Run a Stripe SDK call, translating its exceptions into the card layer's own. */
    private function guard(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (ApiErrorException $e) {
            throw new ProviderRequestException(
                (string) $e->getMessage(),
                $e->getHttpStatus(),
                $e->getStripeCode(),
                $e,
            );
        }
    }

    // ── Real-time (JIT) authorization ─────────────────────────────────────────
    // Stripe delivers `issuing_authorization.request` and waits (~2s) for us to
    // approve/decline via the API. Point that event at POST /api/card/jit/stripe so
    // OUR ledger — via the shared AuthorizeCardAction — is the source of truth, not
    // Stripe's Issuing balance.

    /**
     * Parse an `issuing_authorization.request` event into the neutral auth request.
     * Signature is already verified by the inbound controller before this runs.
     */
    public function parseFundingRequest(string $rawBody, array $headers): CardAuthorizationRequest
    {
        $d = json_decode($rawBody, true) ?: [];
        $obj = $d['data']['object'] ?? [];

        $authId = (string) ($obj['id'] ?? '');
        $card = $obj['card'] ?? null;
        $cardRef = is_array($card) ? (string) ($card['id'] ?? '') : (string) ($card ?? '');

        // Requested value lives under pending_request until the auth is finalised.
        $pending = $obj['pending_request'] ?? [];
        $merchant = $obj['merchant_data'] ?? [];
        $amount = $pending['amount'] ?? ($obj['amount'] ?? 0);
        $currency = $pending['currency'] ?? ($obj['currency'] ?? 'usd');

        $this->jitContext = ['authorization_id' => $authId];

        return new CardAuthorizationRequest(
            cardRef: $cardRef,
            networkAuthId: $authId,
            amountMinor: (string) (int) $amount,
            currency: strtoupper((string) $currency),
            mcc: $merchant['category_code'] ?? ($merchant['category'] ?? null),
            merchant: $merchant['name'] ?? null,
            channel: 'online',
            country: $merchant['country'] ?? null,
        );
    }

    /**
     * Communicate the ledger decision back to Stripe by approving/declining the
     * authorization via the API (within the real-time window), then 200-ack the
     * webhook. A failed API call still 200s so Stripe falls back to its own default.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function formatFundingResponse(AuthorizationResult $result): array
    {
        $authId = $this->jitContext['authorization_id'] ?? null;

        try {
            if ($result->approved) {
                if ($authId) {
                    $this->client->sdk()->issuing->authorizations->approve($authId);
                }

                return ['status' => 200, 'body' => ['approved' => true]];
            }

            if ($authId) {
                $this->client->sdk()->issuing->authorizations->decline($authId);
            }

            return ['status' => 200, 'body' => ['approved' => false, 'decline_reason' => $result->reason]];
        } catch (Throwable $e) {
            Log::warning('Stripe JIT approve/decline call failed', ['reason' => $e->getMessage()]);

            return ['status' => 200, 'body' => ['approved' => $result->approved]];
        }
    }

    // ── Webhooks ──────────────────────────────────────────────────────────────
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        return $this->constructEvent($rawBody, $headers) !== null;
    }

    /** @return list<NormalizedWebhookEvent> */
    public function processWebhook(string $rawBody, array $headers): array
    {
        $event = $this->constructEvent($rawBody, $headers);

        return $event === null ? [] : [$this->normalize($event)];
    }

    public function healthCheck(): ProviderHealth
    {
        $start = microtime(true);

        try {
            $this->client->ping();

            return ProviderHealth::up($this->key, (int) ((microtime(true) - $start) * 1000));
        } catch (Throwable $e) {
            return ProviderHealth::down($this->key, $e->getMessage());
        }
    }

    // ── Stripe-specific internals ─────────────────────────────────────────────
    private function constructEvent(string $rawBody, array $headers): ?Event
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');
        $signature = $this->header($headers, 'stripe-signature');
        if ($secret === '' || $signature === null) {
            return null;
        }

        try {
            return Webhook::constructEvent($rawBody, $signature, $secret);
        } catch (Throwable $e) {
            // Surface the reason (secret mismatch vs timestamp tolerance) for diagnosis;
            // the message contains no secrets.
            Log::warning('Stripe webhook signature verification failed', ['reason' => $e->getMessage()]);

            return null;
        }
    }

    private function normalize(Event $event): NormalizedWebhookEvent
    {
        $object = $event->data->object ?? null;
        $data = $object instanceof StripeObject ? $object->toArray() : (is_array($object) ? $object : []);

        $type = match (true) {
            // Real-time request — answered synchronously by the inbound controller.
            $event->type === 'issuing_authorization.request' => WebhookEventType::AuthorizationRequest,
            // The hold is placed by the synchronous .request path; the follow-up
            // authorization events (incl. declined ones) are informational only.
            str_starts_with($event->type, 'issuing_authorization') => WebhookEventType::Unknown,
            $event->type === 'issuing_transaction.created' => ($data['type'] ?? '') === 'refund'
                ? WebhookEventType::TransactionRefunded
                : WebhookEventType::TransactionCleared,
            $event->type === 'issuing_card.updated' => WebhookEventType::CardUpdated,
            $event->type === 'issuing_card.created' => WebhookEventType::CardCreated,
            default => WebhookEventType::Unknown,
        };

        // Settlement/refund events reference the ORIGINAL authorization (iauth_…) so the
        // hold placed at auth time can be matched; authorization events reference their
        // own id. Card events carry no tx ref.
        $txRef = match (true) {
            str_starts_with($event->type, 'issuing_transaction') => $data['authorization'] ?? null,
            str_starts_with($event->type, 'issuing_authorization') => $data['id'] ?? null,
            default => null,
        };

        $amount = $data['amount'] ?? ($data['pending_request']['amount'] ?? null);
        $currency = $data['currency'] ?? ($data['pending_request']['currency'] ?? null);

        return new NormalizedWebhookEvent(
            provider: $this->key,
            type: $type,
            providerEventId: $event->id,
            providerCardRef: $this->cardRef($data),
            providerTxRef: $txRef !== null ? (string) $txRef : null,
            amountMinor: $amount !== null ? (string) $amount : null,
            currency: $currency !== null ? strtoupper((string) $currency) : null,
            occurredAt: isset($event->created) ? CarbonImmutable::createFromTimestamp($event->created) : null,
            payload: $event->toArray(),
        );
    }

    /** @param array<string, mixed> $c */
    private function mapCard(array $c, bool $reveal = false): CardData
    {
        return new CardData(
            providerCardRef: (string) ($c['id'] ?? ''),
            type: ($c['type'] ?? 'virtual') === 'physical' ? CardType::Physical : CardType::Virtual,
            network: $this->network($c['brand'] ?? null),
            status: $this->mapStatus((string) ($c['status'] ?? 'inactive')),
            last4: $c['last4'] ?? null,
            expMonth: isset($c['exp_month']) ? (int) $c['exp_month'] : null,
            expYear: isset($c['exp_year']) ? (int) $c['exp_year'] : null,
            cardholderRef: $this->cardholderId($c),
            pan: $reveal ? ($c['number'] ?? null) : null,
            cvv: $reveal ? ($c['cvc'] ?? null) : null,
            raw: $c,
        );
    }

    private function mapStatus(string $status): CardStatus
    {
        return match ($status) {
            'active' => CardStatus::Active,
            'inactive' => CardStatus::Frozen,
            'canceled' => CardStatus::Closed,
            default => CardStatus::Inactive,
        };
    }

    /** @param array<string, mixed> $card */
    private function cardholderId(array $card): ?string
    {
        $ch = $card['cardholder'] ?? null;

        return is_array($ch) ? ($ch['id'] ?? null) : (is_string($ch) ? $ch : null);
    }

    /** @param array<string, mixed> $data */
    private function cardRef(array $data): ?string
    {
        if (is_array($data['card'] ?? null)) {
            return $data['card']['id'] ?? null;
        }
        if (is_string($data['card'] ?? null)) {
            return $data['card'];
        }
        if (($data['object'] ?? null) === 'issuing.card') {
            return $data['id'] ?? null;
        }

        return null;
    }

    private function network(?string $brand): CardNetwork
    {
        return CardNetwork::tryFrom(strtolower((string) ($brand ?? $this->config['network'] ?? 'visa'))) ?? CardNetwork::Visa;
    }

    /** @param array<string, mixed> $address @return array<string, string> */
    private function address(array $address): array
    {
        return array_filter([
            'line1' => $address['line1'] ?? null,
            'line2' => $address['line2'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'postal_code' => $address['postal_code'] ?? ($address['postal'] ?? null),
            'country' => $address['country'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** @param array<string, string|list<string>> $headers */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $k => $v) {
            if (strtolower((string) $k) === strtolower($name)) {
                return is_array($v) ? ($v[0] ?? null) : $v;
            }
        }

        return null;
    }
}
