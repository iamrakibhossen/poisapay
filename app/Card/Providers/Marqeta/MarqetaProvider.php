<?php

declare(strict_types=1);

namespace App\Card\Providers\Marqeta;

use App\Card\DTOs\CardData;
use App\Card\DTOs\CardholderData;
use App\Card\DTOs\CardholderResult;
use App\Card\DTOs\CardIssueRequest;
use App\Card\DTOs\NormalizedWebhookEvent;
use App\Card\DTOs\ProviderHealth;
use App\Card\DTOs\ProviderTransactionData;
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
use Throwable;

/**
 * Marqeta Core API adapter (Gateway JIT funding model). Maps the neutral contract
 * onto Marqeta resources: User↔cardholder, Card↔card, cardtransitions for lifecycle.
 * Where Marqeta's exact payload/field paths are not certain they are marked TODO
 * rather than invented — verify against the program's Marqeta docs before go-live.
 *
 * setSpendControls is intentionally NOT a capability yet: Marqeta enforces spend via
 * velocitycontrols/authcontrols/mccgroups (TODO) — CardService degrades gracefully,
 * keeping our local controls authoritative until that mapping is built.
 */
class MarqetaProvider extends AbstractCardProvider
{
    private readonly MarqetaClient $client;

    /** @var array<string, mixed>|null Context stashed by parseFundingRequest for the JIT reply. */
    private ?array $jitContext = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly string $key,
        private readonly array $config = [],
    ) {
        $this->client = new MarqetaClient($config, app(ProviderLogger::class), $key);
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
            ProviderCapability::SyncTransactions,
        ];
    }

    public function createCardholder(CardholderData $data): CardholderResult
    {
        // Deterministic token → idempotent re-provisioning. Email/phone are intentionally
        // NOT sent: Marqeta enforces uniqueness on them (error 400057), so we identify a
        // cardholder by its token + metadata.external_id instead.
        $token = $this->userToken($data->externalId);

        try {
            $res = $this->client->post('users', array_filter([
                'token' => $token,
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'metadata' => ['external_id' => $data->externalId] + $data->metadata,
            ]));
        } catch (ProviderRequestException $e) {
            if (! in_array($e->httpStatus, [400, 409], true) || ! str_contains(strtolower($e->getMessage()), 'exist')) {
                throw $e;
            }
            $res = $this->client->get("users/{$token}"); // already provisioned — reuse it
        }

        return new CardholderResult((string) ($res['token'] ?? $token), $res['status'] ?? null, $res);
    }

    /** Marqeta tokens are ≤36 chars; a UUID fits, otherwise hash the id. */
    private function userToken(string $externalId): string
    {
        return strlen($externalId) <= 36 ? $externalId : substr(hash('sha256', $externalId), 0, 32);
    }

    public function updateCardholder(string $cardholderRef, CardholderData $data): CardholderResult
    {
        $res = $this->client->put("users/{$cardholderRef}", array_filter([
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'email' => $data->email,
            'phone' => $data->phone,
        ]));

        return new CardholderResult($cardholderRef, $res['status'] ?? null, $res);
    }

    public function createVirtualCard(CardIssueRequest $request): CardData
    {
        return $this->issue($request);
    }

    public function createPhysicalCard(CardIssueRequest $request): CardData
    {
        return $this->issue($request);
    }

    private function issue(CardIssueRequest $request): CardData
    {
        $productToken = $this->config['card_product_token'] ?? null;
        if (! $productToken) {
            throw new ProviderRequestException('Set CARD_MARQETA_CARD_PRODUCT_TOKEN to issue Marqeta cards.');
        }

        $res = $this->client->post('cards', array_filter([
            'user_token' => $request->cardholderRef,
            'card_product_token' => $productToken,
            'metadata' => $request->nickname ? ['nickname' => $request->nickname] : null,
        ]));

        return $this->mapCard($res);
    }

    public function getCard(string $providerCardRef, bool $reveal = false): CardData
    {
        $query = $reveal ? ['show_pan' => 'true', 'show_cvv_number' => 'true'] : [];

        return $this->mapCard($this->client->get("cards/{$providerCardRef}", $query), $reveal);
    }

    public function listCards(string $cardholderRef): array
    {
        $res = $this->client->get("cards/user/{$cardholderRef}");

        return array_map(fn (array $c) => $this->mapCard($c), $res['data'] ?? []);
    }

    public function freezeCard(string $providerCardRef, ?string $reason = null): CardData
    {
        return $this->transition($providerCardRef, 'SUSPENDED', $reason);
    }

    public function unfreezeCard(string $providerCardRef): CardData
    {
        return $this->transition($providerCardRef, 'ACTIVE', null);
    }

    public function terminateCard(string $providerCardRef, ?string $reason = null): CardData
    {
        return $this->transition($providerCardRef, 'TERMINATED', $reason);
    }

    public function replaceCard(string $providerCardRef, ?string $reason = null): CardData
    {
        // Marqeta has no single "replace": terminate the old card and issue a new one
        // for the same user + card product.
        $old = $this->client->get("cards/{$providerCardRef}");
        $this->transition($providerCardRef, 'TERMINATED', $reason);

        return $this->mapCard($this->client->post('cards', array_filter([
            'user_token' => $old['user_token'] ?? null,
            'card_product_token' => $old['card_product_token'] ?? ($this->config['card_product_token'] ?? null),
        ])));
    }

    private function transition(string $cardRef, string $state, ?string $reason): CardData
    {
        $res = $this->client->post('cardtransitions', array_filter([
            'card_token' => $cardRef,
            'state' => $state,
            'channel' => 'API',
            'reason_code' => '01',   // required by Marqeta; "01" = requested by you
            'reason' => $reason,
        ]));

        return new CardData(
            providerCardRef: $cardRef,
            type: CardType::Virtual,
            network: $this->network(),
            status: $this->mapState($res['state'] ?? $state),
        );
    }

    public function getTransactions(string $providerCardRef, array $filters = []): array
    {
        $res = $this->client->get('transactions', ['card_token' => $providerCardRef] + $filters);

        return array_map(fn (array $t) => $this->mapTransaction($t), $res['data'] ?? []);
    }

    public function syncTransactions(string $providerCardRef): array
    {
        return $this->getTransactions($providerCardRef);
    }

    // ── Sandbox simulation (test-only; production money movement is network-driven) ──
    // TODO(marqeta): confirm exact simulate payloads/fields against the sandbox docs.
    public function authorize(CardAuthorizationRequest $request): NormalizedWebhookEvent
    {
        $res = $this->client->post('simulate/authorization', [
            'card_token' => $request->cardRef,
            'amount' => (float) $request->amountMinor / 100,
            'mid' => $request->merchant ?? 'MID_TEST',
        ]);

        $tx = $res['transaction'] ?? $res;

        return new NormalizedWebhookEvent(
            provider: $this->key,
            type: WebhookEventType::TransactionAuthorized,
            providerEventId: (string) ($tx['token'] ?? $request->networkAuthId),
            providerCardRef: $request->cardRef,
            providerTxRef: (string) ($tx['token'] ?? $request->networkAuthId),
            amountMinor: $request->amountMinor,
            currency: $request->currency,
            occurredAt: CarbonImmutable::now(),
            payload: $tx,
        );
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        if (! $this->verifyBasicAuth($headers)) {
            return false;
        }

        // Optional HMAC-SHA256 on top of Basic auth.
        // TODO(marqeta): confirm the exact signature header name in the dashboard.
        $secret = $this->config['webhook_secret'] ?? null;
        $sig = $this->header($headers, 'x-marqeta-signature');
        if ($secret && $sig !== null) {
            return hash_equals(base64_encode(hash_hmac('sha256', $rawBody, (string) $secret, true)), $sig)
                || hash_equals(hash_hmac('sha256', $rawBody, (string) $secret), $sig);
        }

        return true;
    }

    public function processWebhook(string $rawBody, array $headers): array
    {
        $data = json_decode($rawBody, true);
        if (! is_array($data)) {
            return [];
        }

        $events = [];

        // TODO(marqeta): confirm the top-level event grouping keys for the program.
        foreach ($data['transactions'] ?? [] as $tx) {
            $events[] = $this->transactionEvent($tx);
        }
        foreach ($data['cardtransitions'] ?? [] as $ct) {
            $events[] = $this->cardTransitionEvent($ct);
        }

        return array_values(array_filter($events));
    }

    public function parseFundingRequest(string $rawBody, array $headers): CardAuthorizationRequest
    {
        $d = json_decode($rawBody, true) ?: [];

        // TODO(marqeta): confirm exact field paths of the gateway JIT message.
        $jit = $d['gpa_order']['jit_funding'] ?? $d['jit_funding'] ?? [];
        $acceptor = $d['card_acceptor'] ?? [];
        $amount = (float) ($d['amount'] ?? ($jit['amount'] ?? 0));

        $this->jitContext = [
            'token' => $jit['token'] ?? ($d['token'] ?? null),
            'amount' => $amount,
            'user_token' => $d['user_token'] ?? ($jit['user_token'] ?? null),
        ];

        return new CardAuthorizationRequest(
            cardRef: (string) ($d['card_token'] ?? ''),
            networkAuthId: (string) ($d['token'] ?? ($jit['token'] ?? '')),
            amountMinor: (string) (int) round($amount * 100),
            currency: (string) ($d['currency_code'] ?? 'USD'),
            mcc: $acceptor['mcc'] ?? null,
            merchant: $acceptor['name'] ?? null,
            channel: 'online',
            country: $acceptor['country'] ?? null,
        );
    }

    public function formatFundingResponse(AuthorizationResult $result): array
    {
        if (! $result->approved) {
            return ['status' => 402, 'body' => ['jit_funding' => null, 'decline_reason' => $result->reason]];
        }

        $ctx = $this->jitContext ?? [];

        // TODO(marqeta): confirm the exact approval echo the gateway expects.
        return ['status' => 200, 'body' => ['jit_funding' => array_filter([
            'token' => $ctx['token'] ?? null,
            'amount' => $ctx['amount'] ?? null,
            'method' => 'pgfs.authorization',
            'user_token' => $ctx['user_token'] ?? null,
        ], fn ($v) => $v !== null)]];
    }

    public function healthCheck(): ProviderHealth
    {
        try {
            $this->client->get('ping');

            return ProviderHealth::up($this->key);
        } catch (Throwable $e) {
            return ProviderHealth::down($this->key, $e->getMessage());
        }
    }

    // ── Mapping helpers ──────────────────────────────────────────────────────
    /** @param array<string, mixed> $res */
    private function mapCard(array $res, bool $reveal = false): CardData
    {
        [$month, $year] = $this->parseExpiration($res['expiration'] ?? null);

        return new CardData(
            providerCardRef: (string) ($res['token'] ?? ''),
            type: CardType::Virtual,
            network: $this->network(),
            status: $this->mapState((string) ($res['state'] ?? 'UNACTIVATED')),
            last4: $res['last_four'] ?? null,
            expMonth: $month,
            expYear: $year,
            cardholderRef: $res['user_token'] ?? null,
            pan: $reveal ? ($res['pan'] ?? null) : null,
            cvv: $reveal ? ($res['cvv_number'] ?? null) : null,
            raw: $res,
        );
    }

    /** @param array<string, mixed> $t */
    private function mapTransaction(array $t): ProviderTransactionData
    {
        return new ProviderTransactionData(
            providerTxRef: (string) ($t['token'] ?? ''),
            type: (string) ($t['type'] ?? 'authorization'),
            amountMinor: (string) (int) round((float) ($t['amount'] ?? 0) * 100),
            currency: (string) ($t['currency_code'] ?? 'USD'),
            status: (string) ($t['state'] ?? ''),
            mcc: $t['card_acceptor']['mcc'] ?? null,
            merchant: $t['card_acceptor']['name'] ?? null,
            occurredAt: isset($t['created_time']) ? CarbonImmutable::parse($t['created_time']) : null,
            raw: $t,
        );
    }

    /** @param array<string, mixed> $tx */
    private function transactionEvent(array $tx): ?NormalizedWebhookEvent
    {
        $type = match (true) {
            str_contains((string) ($tx['type'] ?? ''), 'clearing') => WebhookEventType::TransactionCleared,
            str_contains((string) ($tx['type'] ?? ''), 'refund') => WebhookEventType::TransactionRefunded,
            str_contains((string) ($tx['type'] ?? ''), 'reversal') => WebhookEventType::TransactionReversed,
            str_contains((string) ($tx['type'] ?? ''), 'authorization') => WebhookEventType::TransactionAuthorized,
            default => WebhookEventType::Unknown,
        };

        // Settle/refund/reverse must reference the ORIGINAL auth token (our network_auth_id).
        $ref = $tx['preceding_related_transaction_token'] ?? $tx['original_transaction_token'] ?? $tx['token'] ?? null;

        return new NormalizedWebhookEvent(
            provider: $this->key,
            type: $type,
            providerEventId: (string) ($tx['token'] ?? ''),
            providerCardRef: $tx['card_token'] ?? null,
            providerTxRef: $ref !== null ? (string) $ref : null,
            amountMinor: isset($tx['amount']) ? (string) (int) round((float) $tx['amount'] * 100) : null,
            currency: $tx['currency_code'] ?? null,
            occurredAt: CarbonImmutable::now(),
            payload: $tx,
        );
    }

    /** @param array<string, mixed> $ct */
    private function cardTransitionEvent(array $ct): NormalizedWebhookEvent
    {
        $type = match ((string) ($ct['state'] ?? '')) {
            'SUSPENDED', 'LIMITED' => WebhookEventType::CardFrozen,
            'ACTIVE' => WebhookEventType::CardUnfrozen,
            'TERMINATED' => WebhookEventType::CardClosed,
            default => WebhookEventType::CardUpdated,
        };

        return new NormalizedWebhookEvent(
            provider: $this->key,
            type: $type,
            providerEventId: (string) ($ct['token'] ?? ''),
            providerCardRef: $ct['card_token'] ?? null,
            occurredAt: CarbonImmutable::now(),
            payload: $ct,
        );
    }

    private function mapState(string $state): CardStatus
    {
        return match (strtoupper($state)) {
            'ACTIVE' => CardStatus::Active,
            'SUSPENDED', 'LIMITED' => CardStatus::Frozen,
            'TERMINATED' => CardStatus::Closed,
            default => CardStatus::Inactive,
        };
    }

    /** @return array{0: ?int, 1: ?int} [month, year] from Marqeta 'MMYY'. */
    private function parseExpiration(?string $expiration): array
    {
        if ($expiration === null || strlen($expiration) !== 4) {
            return [null, null];
        }

        return [(int) substr($expiration, 0, 2), 2000 + (int) substr($expiration, 2, 2)];
    }

    private function network(): CardNetwork
    {
        return CardNetwork::tryFrom(strtolower((string) ($this->config['network'] ?? 'visa'))) ?? CardNetwork::Visa;
    }

    /** @param array<string, string|list<string>> $headers */
    private function verifyBasicAuth(array $headers): bool
    {
        $user = $this->config['inbound_username'] ?? null;
        $pass = $this->config['inbound_password'] ?? null;
        if (! $user || ! $pass) {
            return true; // No inbound creds configured — rely on network-level protection.
        }

        $auth = $this->header($headers, 'authorization');
        if ($auth === null || ! str_starts_with($auth, 'Basic ')) {
            return false;
        }

        return hash_equals("{$user}:{$pass}", (string) base64_decode(substr($auth, 6), true));
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
