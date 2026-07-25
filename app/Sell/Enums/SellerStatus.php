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

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
