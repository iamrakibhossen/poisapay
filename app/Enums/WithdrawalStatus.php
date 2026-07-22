<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Withdrawal lifecycle (TDD §6.3). Reserve-before-sign (A3). */
enum WithdrawalStatus: string
{
    use HasMeta;

    case Pending = 'pending';
    case Review = 'review';
    case Approved = 'approved';
    case Signing = 'signing';
    case Broadcast = 'broadcast';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Signing, self::Broadcast => 'warning',
            self::Review => 'info',
            self::Approved => 'primary',
            self::Completed => 'success',
            self::Failed, self::Cancelled => 'danger',
        };
    }

    /** Funds are still locked and could be released on cancel/fail before broadcast. */
    public function isReversibleLock(): bool
    {
        return in_array($this, [self::Pending, self::Review, self::Approved, self::Signing], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }
}
