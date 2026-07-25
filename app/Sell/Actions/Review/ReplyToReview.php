<?php

declare(strict_types=1);

namespace App\Sell\Actions\Review;

use App\Sell\Events\ReviewReplied;
use App\Sell\Models\Review;

/** A seller replies (once) to a review on one of their products. */
class ReplyToReview
{
    public function execute(Review $review, string $reply): Review
    {
        $review->update([
            'seller_reply' => $reply,
            'seller_replied_at' => now(),
        ]);

        ReviewReplied::dispatch($review);

        return $review->refresh();
    }
}
