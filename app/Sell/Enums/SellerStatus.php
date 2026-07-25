<?php

declare(strict_types=1);

namespace App\Sell\Enums;

/** Seller lifecycle. */
enum SellerStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function canSell(): bool
    {
        return $this === self::Approved;
    }

    /** May a user (re)submit an application from this state? */
    public function canApply(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
