<?php

declare(strict_types=1);

namespace App\Card\Providers\Mock;

use App\Card\DTOs\CardData;
use App\Card\DTOs\CardholderData;
use App\Card\DTOs\CardholderResult;
use App\Card\DTOs\CardIssueRequest;
use App\Card\DTOs\NormalizedWebhookEvent;
use App\Card\DTOs\ProviderHealth;
use App\Card\DTOs\SpendControlData;
use App\Card\Enums\ProviderCapability;
use App\Card\Enums\WebhookEventType;
use App\Card\Providers\AbstractCardProvider;
use App\Domain\Card\AuthorizationResult;
use App\Domain\Card\CardAuthorizationRequest;
use App\Enums\CardNetwork;
use App\Enums\CardStatus;
use App\Enums\CardType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Fully-simulated provider — no network calls. Card data is derived deterministically
 * from the token so reveals are stable, and it signs its own webhooks/JIT bodies so
 * the inbound pipeline can be exercised for real. The platform runs end-to-end on this.
 */
class MockCardProvider extends AbstractCardProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly string $key,
        private readonly array $config = [],
    ) {}

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
            ProviderCapability::SpendControls,
            ProviderCapability::RevealPan,
            ProviderCapability::JitFunding,
            ProviderCapability::Webhooks,
            ProviderCapability::SyncTransactions,
            ProviderCapability::Refund,
            ProviderCapability::Reverse,
        ];
    }

    public function createCardholder(CardholderData $data): CardholderResult
    {
        return new CardholderResult('usr_mock_'.Str::lower(Str::random(20)), 'ACTIVE', ['external_id' => $data->externalId]);
    }

    public function updateCardholder(string $cardholderRef, CardholderData $data): CardholderResult
    {
        return new CardholderResult($cardholderRef, 'ACTIVE', ['external_id' => $data->externalId]);
    }

    public function createVirtualCard(CardIssueRequest $request): CardData
    {
        return $this->issue($request);
    }

    public function createPhysicalCard(CardIssueRequest $request): CardData
    {
        return $this->issue($request);
    }

    public function getCard(string $providerCardRef, bool $reveal = false): CardData
    {
        $card = $this->deriveCard($providerCardRef, CardType::Virtual, CardNetwork::Visa, CardStatus::Active);

        if (! $reveal) {
            return $card;
        }

        return new CardData(
            providerCardRef: $card->providerCardRef,
            type: $card->type,
            network: $card->network,
            status: $card->status,
            last4: $card->last4,
            expMonth: $card->expMonth,
            expYear: $card->expYear,
            pan: $this->derivePan($providerCardRef),
            cvv: str_pad((string) (crc32('cvv'.$providerCardRef) % 1000), 3, '0', STR_PAD_LEFT),
        );
    }

    public function listCards(string $cardholderRef): array
    {
        return [];
    }

    public function freezeCard(string $providerCardRef, ?string $reason = null): CardData
    {
        return $this->deriveCard($providerCardRef, CardType::Virtual, CardNetwork::Visa, CardStatus::Frozen);
    }

    public function unfreezeCard(string $providerCardRef): CardData
    {
        return $this->deriveCard($providerCardRef, CardType::Virtual, CardNetwork::Visa, CardStatus::Active);
    }

    public function terminateCard(string $providerCardRef, ?string $reason = null): CardData
    {
        return $this->deriveCard($providerCardRef, CardType::Virtual, CardNetwork::Visa, CardStatus::Closed);
    }

    public function replaceCard(string $providerCardRef, ?string $reason = null): CardData
    {
        return $this->deriveCard('card_mock_'.Str::lower(Str::random(24)), CardType::Virtual, CardNetwork::Visa, CardStatus::Inactive);
    }

    public function setSpendControls(string $providerCardRef, SpendControlData $controls): void
    {
        //
    }

    public function getTransactions(string $providerCardRef, array $filters = []): array
    {
        return [];
    }

    public function syncTransactions(string $providerCardRef): array
    {
        return [];
    }

    public function authorize(CardAuthorizationRequest $request): NormalizedWebhookEvent
    {
        return new NormalizedWebhookEvent(
            provider: $this->key,
            type: WebhookEventType::TransactionAuthorized,
            providerEventId: 'evt_'.$request->networkAuthId,
            providerCardRef: $request->cardRef,
            providerTxRef: $request->networkAuthId,
            amountMinor: $request->amountMinor,
            currency: $request->currency,
            occurredAt: CarbonImmutable::now(),
            payload: (array) $request,
        );
    }

    public function capture(string $providerTxRef, ?Money $amount = null): NormalizedWebhookEvent
    {
        return $this->txEvent(WebhookEventType::TransactionCleared, $providerTxRef, $amount);
    }

    public function refund(string $providerTxRef, ?Money $amount = null): NormalizedWebhookEvent
    {
        return $this->txEvent(WebhookEventType::TransactionRefunded, $providerTxRef, $amount);
    }

    public function reverse(string $providerTxRef, ?Money $amount = null): NormalizedWebhookEvent
    {
        return $this->txEvent(WebhookEventType::TransactionReversed, $providerTxRef, $amount);
    }

    private function txEvent(WebhookEventType $type, string $providerTxRef, ?Money $amount): NormalizedWebhookEvent
    {
        return new NormalizedWebhookEvent(
            provider: $this->key,
            type: $type,
            providerEventId: 'evt_'.$type->value.'_'.$providerTxRef,
            providerTxRef: $providerTxRef,
            amountMinor: $amount?->baseString(),
            occurredAt: CarbonImmutable::now(),
        );
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $sent = $this->header($headers, 'x-mock-signature');

        return $sent !== null && hash_equals($this->sign($rawBody), $sent);
    }

    public function processWebhook(string $rawBody, array $headers): array
    {
        $data = json_decode($rawBody, true);

        if (! is_array($data)) {
            return [];
        }

        return [new NormalizedWebhookEvent(
            provider: $this->key,
            type: WebhookEventType::tryFrom((string) ($data['type'] ?? '')) ?? WebhookEventType::Unknown,
            providerEventId: (string) ($data['id'] ?? Str::uuid()),
            providerCardRef: $data['card_ref'] ?? null,
            providerTxRef: $data['tx_ref'] ?? null,
            amountMinor: isset($data['amount_minor']) ? (string) $data['amount_minor'] : null,
            currency: $data['currency'] ?? null,
            occurredAt: CarbonImmutable::now(),
            payload: $data,
        )];
    }

    public function parseFundingRequest(string $rawBody, array $headers): CardAuthorizationRequest
    {
        $d = json_decode($rawBody, true) ?: [];

        return new CardAuthorizationRequest(
            cardRef: (string) ($d['card_ref'] ?? ''),
            networkAuthId: (string) ($d['network_auth_id'] ?? Str::uuid()),
            amountMinor: (string) ($d['amount_minor'] ?? '0'),
            currency: (string) ($d['currency'] ?? 'USD'),
            mcc: $d['mcc'] ?? null,
            merchant: $d['merchant'] ?? null,
            channel: (string) ($d['channel'] ?? 'online'),
            country: $d['country'] ?? null,
        );
    }

    public function formatFundingResponse(AuthorizationResult $result): array
    {
        return ['status' => $result->approved ? 200 : 402, 'body' => $result->toResponse()];
    }

    public function healthCheck(): ProviderHealth
    {
        return ProviderHealth::up($this->key, 0);
    }

    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) ($this->config['webhook_secret'] ?? 'mock-webhook-secret'));
    }

    private function deriveCard(string $ref, CardType $type, CardNetwork $network, CardStatus $status, ?string $cardholderRef = null): CardData
    {
        $seed = crc32($ref);

        return new CardData(
            providerCardRef: $ref,
            type: $type,
            network: $network,
            status: $status,
            last4: str_pad((string) ($seed % 10000), 4, '0', STR_PAD_LEFT),
            expMonth: ($seed % 12) + 1,
            expYear: CarbonImmutable::now()->addYears(3)->year,
            cardholderRef: $cardholderRef,
        );
    }

    private function issue(CardIssueRequest $request): CardData
    {
        return $this->deriveCard('card_mock_'.Str::lower(Str::random(24)), $request->type, $request->network, CardStatus::Inactive, $request->cardholderRef);
    }

    private function derivePan(string $ref): string
    {
        return '400000'
            .str_pad((string) (crc32('pan'.$ref) % 1000000), 6, '0', STR_PAD_LEFT)
            .str_pad((string) (crc32($ref) % 10000), 4, '0', STR_PAD_LEFT);
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
