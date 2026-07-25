<?php

declare(strict_types=1);

namespace App\Sell\Events;

use App\Sell\Models\Review;
use Illuminate\Database\Eloquent\Model;

/** A seller replied to a review. Audited. */
class ReviewReplied extends SellDomainEvent
{
    public function __construct(public readonly Review $review) {}

    public function auditAction(): string
    {
        return 'review.replied';
    }

    public function auditSubject(): ?Model
    {
        return $this->review;
    }

    public function auditData(): array
    {
        return ['product_id' => $this->review->product_id];
    }
}
