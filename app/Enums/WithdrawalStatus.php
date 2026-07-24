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

    /** A terminal state that did NOT succeed — drives the failed progress rendering. */
    public function isFailure(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled], true);
    }

    /**
     * Position in the 4-stage progress tracker (Requested → Approved → Sent →
     * Completed): 1..4 for the currently-reached stage, 0 for a terminal failure.
     * Presentation only — the enum cases remain the authoritative state.
     */
    public function progressStep(): int
    {
        return match ($this) {
            self::Pending, self::Review => 1,
            self::Approved => 2,
            self::Signing, self::Broadcast => 3,
            self::Completed => 4,
            self::Failed, self::Cancelled => 0,
        };
    }
}
