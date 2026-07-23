<?php

declare(strict_types=1);

namespace App\Card\Enums;

use App\Enums\Concerns\HasMeta;

/** Canonical inbound event types; every provider maps its own events onto these. */
enum WebhookEventType: string
{
    use HasMeta;

    case CardCreated = 'card.created';
    case CardUpdated = 'card.updated';
    case CardFrozen = 'card.frozen';
    case CardUnfrozen = 'card.unfrozen';
    case CardReplaced = 'card.replaced';
    case CardClosed = 'card.closed';
    // A real-time authorisation request the provider expects us to answer
    // synchronously (approve/decline) within its window — handled inline, not queued.
    case AuthorizationRequest = 'authorization.request';
    case TransactionAuthorized = 'transaction.authorized';
    case TransactionCleared = 'transaction.cleared';
    case TransactionRefunded = 'transaction.refunded';
    case TransactionReversed = 'transaction.reversed';
    case ProviderError = 'provider.error';
    case Unknown = 'unknown';
}
