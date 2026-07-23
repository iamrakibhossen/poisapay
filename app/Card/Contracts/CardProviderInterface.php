<?php

declare(strict_types=1);

namespace App\Card\Contracts;

use App\Card\DTOs\CardData;
use App\Card\DTOs\CardholderData;
use App\Card\DTOs\CardholderResult;
use App\Card\DTOs\CardIssueRequest;
use App\Card\DTOs\NormalizedWebhookEvent;
use App\Card\DTOs\ProviderHealth;
use App\Card\DTOs\ProviderTransactionData;
use App\Card\DTOs\RevealSession;
use App\Card\DTOs\SpendControlData;
use App\Card\Enums\ProviderCapability;
use App\Domain\Card\AuthorizationResult;
use App\Domain\Card\CardAuthorizationRequest;
use App\Support\Money;

/**
 * The one contract every card provider implements. Only neutral DTOs cross this
 * boundary; unsupported operations throw FeatureNotSupportedException.
 *
 * @phpstan-type Headers array<string, string|list<string>>
 */
interface CardProviderInterface
{
    public function key(): string;

    /** @return list<ProviderCapability> */
    public function capabilities(): array;

    public function supports(ProviderCapability $capability): bool;

    public function createCardholder(CardholderData $data): CardholderResult;

    public function updateCardholder(string $cardholderRef, CardholderData $data): CardholderResult;

    public function createVirtualCard(CardIssueRequest $request): CardData;

    public function createPhysicalCard(CardIssueRequest $request): CardData;

    /** $reveal returns transient PAN/CVV (PCI-scoped); never persist them. */
    public function getCard(string $providerCardRef, bool $reveal = false): CardData;

    /**
     * Mint a short-lived, single-card-scoped session so the USER'S BROWSER can fetch
     * full card details straight from the issuer (PCI SAQ-A). Real providers return
     * only a client secret (never a PAN); $context carries provider-specific handshake
     * data such as Stripe's client-generated `nonce`.
     *
     * @param  array<string, mixed>  $context
     */
    public function createRevealSession(string $providerCardRef, array $context = []): RevealSession;

    /** @return list<CardData> */
    public function listCards(string $cardholderRef): array;

    public function freezeCard(string $providerCardRef, ?string $reason = null): CardData;

    public function unfreezeCard(string $providerCardRef): CardData;

    public function terminateCard(string $providerCardRef, ?string $reason = null): CardData;

    public function replaceCard(string $providerCardRef, ?string $reason = null): CardData;

    public function setSpendControls(string $providerCardRef, SpendControlData $controls): void;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<ProviderTransactionData>
     */
    public function getTransactions(string $providerCardRef, array $filters = []): array;

    /** @return list<ProviderTransactionData> */
    public function syncTransactions(string $providerCardRef): array;

    /** Null when the provider is not the balance source of truth (JIT model). */
    public function syncBalance(string $cardholderRef): ?Money;

    public function authorize(CardAuthorizationRequest $request): NormalizedWebhookEvent;

    public function capture(string $providerTxRef, ?Money $amount = null): NormalizedWebhookEvent;

    public function refund(string $providerTxRef, ?Money $amount = null): NormalizedWebhookEvent;

    public function reverse(string $providerTxRef, ?Money $amount = null): NormalizedWebhookEvent;

    /** @param array<string, string|list<string>> $headers */
    public function verifyWebhook(string $rawBody, array $headers): bool;

    /**
     * @param  array<string, string|list<string>>  $headers
     * @return list<NormalizedWebhookEvent>
     */
    public function processWebhook(string $rawBody, array $headers): array;

    public function supportsJitFunding(): bool;

    /** @param array<string, string|list<string>> $headers */
    public function parseFundingRequest(string $rawBody, array $headers): CardAuthorizationRequest;

    /** @return array{status:int, body:array<string,mixed>} */
    public function formatFundingResponse(AuthorizationResult $result): array;

    public function healthCheck(): ProviderHealth;
}
