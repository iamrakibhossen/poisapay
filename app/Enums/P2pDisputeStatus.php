<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/**
 * Operator-side dispute case lifecycle. A resolution maps to a force
 * release (buyer wins) or force cancel (seller wins) on the order.
 */
enum P2pDisputeStatus: string
{
    use HasMeta;

    case Open = 'open';
    case UnderReview = 'under_review';
    case ResolvedBuyer = 'resolved_buyer';
    case ResolvedSeller = 'resolved_seller';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::UnderReview], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::UnderReview => 'warning',
            self::ResolvedBuyer, self::ResolvedSeller => 'success',
            self::Cancelled => 'muted',
        };
    }
}
