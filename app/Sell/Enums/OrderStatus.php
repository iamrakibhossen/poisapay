<?php

declare(strict_types=1);

namespace App\Sell\Enums;

/**
 * Order lifecycle as an explicit, extensible state machine. Transitions are
 * declared here (never hardcoded at call sites), so adding a state or edge is a
 * single-file change. Actions call {@see canTransitionTo()} before mutating.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Processing, self::Completed, self::Refunded, self::PartiallyRefunded],
            self::Processing => [self::Shipped, self::Completed, self::Refunded, self::PartiallyRefunded],
            self::Shipped => [self::Delivered, self::Refunded, self::PartiallyRefunded],
            self::Delivered => [self::Completed, self::Refunded, self::PartiallyRefunded],
            self::Completed => [self::Refunded, self::PartiallyRefunded],
            self::PartiallyRefunded => [self::Refunded],
            self::Refunded, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    /** Money has been captured (earnings exist for this order). */
    public function isPaid(): bool
    {
        return ! in_array($this, [self::Pending, self::Cancelled], true);
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Processing => 'warning',
            self::Paid, self::Delivered, self::Completed => 'success',
            self::Shipped => 'info',
            self::Cancelled, self::Refunded, self::PartiallyRefunded => 'danger',
        };
    }
}
