<?php

declare(strict_types=1);

namespace App\Card\Inbound;

use App\Card\DTOs\NormalizedWebhookEvent;
use App\Card\Enums\WebhookEventType;
use App\Domain\Card\AuthorizeCardAction;
use App\Domain\Card\CardAuthorizationRequest;
use App\Domain\Card\RefundCardAuthAction;
use App\Domain\Card\ReverseCardAuthAction;
use App\Domain\Card\SettleCardAuthAction;
use App\Enums\CardAuthStatus;
use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardAuthorization;

/** Maps a canonical inbound event to the ledger/lifecycle action. Idempotent. */
class WebhookEventRouter
{
    public function __construct(
        private readonly SettleCardAuthAction $settle,
        private readonly RefundCardAuthAction $refund,
        private readonly ReverseCardAuthAction $reverse,
        private readonly AuthorizeCardAction $authorize,
    ) {}

    /** @return string processed|ignored */
    public function handle(NormalizedWebhookEvent $event): string
    {
        return match ($event->type) {
            WebhookEventType::TransactionCleared => $this->onCleared($event),
            WebhookEventType::TransactionRefunded => $this->onRefunded($event),
            WebhookEventType::TransactionReversed => $this->onReversed($event),
            WebhookEventType::TransactionAuthorized => $this->onAuthorized($event),
            WebhookEventType::CardFrozen => $this->setCardStatus($event, CardStatus::Frozen),
            WebhookEventType::CardUnfrozen => $this->setCardStatus($event, CardStatus::Active),
            WebhookEventType::CardClosed => $this->setCardStatus($event, CardStatus::Closed),
            default => 'ignored',
        };
    }

    private function onCleared(NormalizedWebhookEvent $event): string
    {
        $auth = $this->findAuth($event);
        if (! $auth || $auth->status !== CardAuthStatus::Approved) {
            return 'ignored';
        }
        $this->settle->execute($auth);

        return 'processed';
    }

    private function onRefunded(NormalizedWebhookEvent $event): string
    {
        $auth = $this->findAuth($event);
        if (! $auth || $auth->status !== CardAuthStatus::Settled) {
            return 'ignored';
        }
        $this->refund->execute($auth);

        return 'processed';
    }

    private function onReversed(NormalizedWebhookEvent $event): string
    {
        $auth = $this->findAuth($event);
        if (! $auth || $auth->status !== CardAuthStatus::Approved) {
            return 'ignored';
        }
        $this->reverse->execute($auth);

        return 'processed';
    }

    private function onAuthorized(NormalizedWebhookEvent $event): string
    {
        // JIT providers authorise synchronously; an async auth event is only acted on
        // when we have not already recorded it (non-JIT providers).
        if (! $event->providerTxRef || $this->findAuth($event)) {
            return 'ignored';
        }

        $raw = $event->payload;
        $this->authorize->authorize(new CardAuthorizationRequest(
            cardRef: (string) $event->providerCardRef,
            networkAuthId: $event->providerTxRef,
            amountMinor: (string) ($event->amountMinor ?? '0'),
            currency: (string) ($event->currency ?? 'USD'),
            mcc: $raw['mcc'] ?? null,
            merchant: $raw['merchant'] ?? null,
            channel: (string) ($raw['channel'] ?? 'online'),
            country: $raw['country'] ?? null,
        ));

        return 'processed';
    }

    private function setCardStatus(NormalizedWebhookEvent $event, CardStatus $status): string
    {
        $card = $this->findCard($event);
        if (! $card || $card->status === CardStatus::Closed) {
            return 'ignored';
        }
        $card->update(array_filter([
            'status' => $status,
            'closed_at' => $status === CardStatus::Closed ? now() : null,
        ], fn ($v) => $v !== null));

        return 'processed';
    }

    private function findAuth(NormalizedWebhookEvent $event): ?CardAuthorization
    {
        return $event->providerTxRef
            ? CardAuthorization::where('network_auth_id', $event->providerTxRef)->first()
            : null;
    }

    private function findCard(NormalizedWebhookEvent $event): ?Card
    {
        return $event->providerCardRef
            ? Card::where('issuer_card_ref', $event->providerCardRef)->first()
            : null;
    }
}
