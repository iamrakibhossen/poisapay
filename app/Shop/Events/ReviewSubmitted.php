<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Shop\Models\Review;
use Illuminate\Database\Eloquent\Model;

/** A buyer submitted a product review. Audited. */
class ReviewSubmitted extends ShopDomainEvent
{
    public function __construct(public readonly Review $review) {}

    public function auditAction(): string
    {
        return 'review.submitted';
    }

    public function auditSubject(): ?Model
    {
        return $this->review;
    }

    public function auditData(): array
    {
        return ['product_id' => $this->review->product_id, 'rating' => $this->review->rating];
    }
}
