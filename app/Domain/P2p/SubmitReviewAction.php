<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Models\P2pOrder;
use App\Models\P2pReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a party's feedback on a settled P2P trade and refreshes the ratee's
 * cached reputation aggregates. One review per rater per order — enforced under
 * a row lock and backed by a unique constraint.
 */
class SubmitReviewAction
{
    public function __construct(private readonly MerchantStatsService $stats) {}

    public function execute(P2pOrder $order, User $rater, int $rating, ?string $comment = null): P2pReview
    {
        if (! feature('p2p_enabled', false)) {
            throw new RuntimeException('P2P marketplace is not enabled.');
        }

        if (! $order->status->isSuccessful()) {
            throw new RuntimeException('You can only review a completed trade.');
        }

        $raterId = (string) $rater->getKey();
        if ($raterId !== $order->buyer_id && $raterId !== $order->seller_id) {
            throw new RuntimeException('Only a party to this trade can leave a review.');
        }

        $rating = max(1, min(5, $rating));
        $rateeId = $raterId === $order->buyer_id ? $order->seller_id : $order->buyer_id;
        $comment = $comment !== null ? trim($comment) : null;

        return DB::transaction(function () use ($order, $raterId, $rateeId, $rating, $comment): P2pReview {
            $already = P2pReview::query()
                ->where('order_id', $order->getKey())
                ->where('rater_id', $raterId)
                ->lockForUpdate()
                ->exists();
            if ($already) {
                throw new RuntimeException('You have already reviewed this trade.');
            }

            $review = P2pReview::create([
                'order_id' => $order->getKey(),
                'rater_id' => $raterId,
                'ratee_id' => $rateeId,
                'rating' => $rating,
                'is_positive' => $rating >= 4,
                'comment' => $comment !== '' ? $comment : null,
            ]);

            $this->stats->recordReview($rateeId);

            return $review;
        });
    }
}
