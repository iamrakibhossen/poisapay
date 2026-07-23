<?php

declare(strict_types=1);

namespace App\Card\Providers;

use App\Card\Contracts\CardProviderInterface;
use App\Card\DTOs\CardData;
use App\Card\DTOs\CardholderData;
use App\Card\DTOs\CardholderResult;
use App\Card\DTOs\CardIssueRequest;
use App\Card\DTOs\NormalizedWebhookEvent;
use App\Card\DTOs\ProviderHealth;
use App\Card\DTOs\RevealSession;
use App\Card\DTOs\SpendControlData;
use App\Card\Enums\ProviderCapability;
use App\Card\Exceptions\FeatureNotSupportedException;
use App\Domain\Card\AuthorizationResult;
use App\Domain\Card\CardAuthorizationRequest;
use App\Support\Money;

/** Every op throws FeatureNotSupportedException by default; adapters override what they support. */
abstract class AbstractCardProvider implements CardProviderInterface
{
    abstract public function key(): string;

    /** @return list<ProviderCapability> */
    abstract public function capabilities(): array;

    public function supports(ProviderCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    protected function requireCapability(ProviderCapability $capability): void
    {
        if (! $this->supports($capability)) {
            throw FeatureNotSupportedException::for($this->key(), $capability);
        }
    }

    protected function unsupported(string $feature): never
    {
        throw new FeatureNotSupportedException("Provider [{$this->key()}] does not support [{$feature}].");
    }

    public function createCardholder(CardholderData $data): CardholderResult
    {
        $this->unsupported('createCardholder');
    }

    public function updateCardholder(string $cardholderRef, CardholderData $data): CardholderResult
    {
        $this->unsupported('updateCardholder');
    }

    public function createVirtualCard(CardIssueRequest $request): CardData
    {
        $this->unsupported('createVirtualCard');
    }

    public function createPhysicalCard(CardIssueRequest $request): CardData
    {
        $this->unsupported('createPhysicalCard');
    }

    public function getCard(string $providerCardRef, bool $reveal = false): CardData
    {
        $this->unsupported('getCard');
    }

    public function createRevealSession(string $providerCardRef, array $context = []): RevealSession
    {
        $this->unsupported('createRevealSession');
    }

    public function listCards(string $cardholderRef): array
    {
        $this->unsupported('listCards');
    }

    public function freezeCard(string $providerCardRef, ?string $reason = null): CardData
    {
        $this->unsupported('freezeCard');
    }

    public function unfreezeCard(string $providerCardRef): CardData
    {
        $this->unsupported('unfreezeCard');
    }

    public function terminateCard(string $providerCardRef, ?string $reason = null): CardData
    {
        $this->unsupported('terminateCard');
    }

    public function replaceCard(string $providerCardRef, ?string $reason = null): CardData
    {
        $this->unsupported('replaceCard');
    }

    public function setSpendControls(string $providerCardRef, SpendControlData $controls): void
    {
        $this->unsupported('setSpendControls');
    }

    public function getTransactions(string $providerCardRef, array $filters = []): array
    {
        $this->unsupported('getTransactions');
    }

    public function syncTransactions(string $providerCardRef): array
    {
        $this->unsupported('syncTransactions');
    }

    public function syncBalance(string $cardholderRef): ?Money
    {
        return null;
    }

    public function authorize(CardAuthorizationRequest $request): NormalizedWebhookEvent
    {
        $this->unsupported('authorize');
    }

    public function capture(string $providerTxRef, ?Money $amount = null): NormalizedWebhookEvent
    {
        $this->unsupported('capture');
    }

    public function refund(string $providerTxRef, ?Money $amount = null): NormalizedWebhookEvent
    {
        $this->unsupported('refund');
    }

    public function reverse(string $providerTxRef, ?Money $amount = null): NormalizedWebhookEvent
    {
        $this->unsupported('reverse');
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        return false;
    }

    public function processWebhook(string $rawBody, array $headers): array
    {
        $this->unsupported('processWebhook');
    }

    public function supportsJitFunding(): bool
    {
        return $this->supports(ProviderCapability::JitFunding);
    }

    public function parseFundingRequest(string $rawBody, array $headers): CardAuthorizationRequest
    {
        $this->unsupported('parseFundingRequest');
    }

    public function formatFundingResponse(AuthorizationResult $result): array
    {
        $this->unsupported('formatFundingResponse');
    }

    abstract public function healthCheck(): ProviderHealth;
}
