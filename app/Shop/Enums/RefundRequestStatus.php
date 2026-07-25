<?php

declare(strict_types=1);

namespace App\Shop\Enums;

/**
 * Lifecycle of a buyer refund request, as an explicit state machine (mirrors
 * {@see OrderStatus}). Approving executes the ledger refund synchronously, so
 * "approved" collapses straight into Refunded.
 *
 *   Requested ─seller/admin─▶ Refunded | Rejected
 *   Requested ─buyer─▶ Cancelled          Requested ─SLA job─▶ Escalated
 *   Rejected  ─buyer─▶ Escalated          Escalated ─admin─▶ Refunded | Rejected
 */
enum RefundRequestStatus: string
{
    case Requested = 'requested';
    case Refunded = 'refunded';
    case Rejected = 'rejected';
    case Escalated = 'escalated';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Requested => [self::Refunded, self::Rejected, self::Escalated, self::Cancelled],
            self::Rejected => [self::Escalated],
            self::Escalated => [self::Refunded, self::Rejected],
            self::Refunded, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Rejected, self::Escalated], true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Escalated => 'info',
            self::Refunded => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
