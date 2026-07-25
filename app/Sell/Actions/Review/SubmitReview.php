<?php

declare(strict_types=1);

namespace App\Sell\Actions\Review;

use App\Models\User;
use App\Sell\Events\ReviewSubmitted;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Order;
use App\Sell\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * A buyer reviews a product they bought. The order proves the purchase; a unique
 * (order_id, product_id) enforces one review per purchase (a re-submit updates
 * the existing review). Only the buyer who placed the paid order may review.
 */
class SubmitReview
{
    public function execute(User $buyer, Order $order, string $productId, int $rating, ?string $title, ?string $body): Review
    {
        if ($order->buyer_user_id !== $buyer->getKey() || ! $order->status->isPaid()) {
            throw SellException::notBuyable('you can only review your own purchase');
        }
        if (! $order->items()->where('product_id', $productId)->exists()) {
            throw SellException::notBuyable('that product is not in this order');
        }

        $rating = max(1, min(5, $rating));

        return DB::transaction(function () use ($order, $buyer, $productId, $rating, $title, $body): Review {
            $review = Review::updateOrCreate(
                ['order_id' => $order->getKey(), 'product_id' => $productId],
                [
                    'seller_id' => $order->seller_id,
                    'buyer_user_id' => $buyer->getKey(),
                    'rating' => $rating,
                    'title' => $title,
                    'body' => $body,
                    'status' => 'published',
                ],
            );

            ReviewSubmitted::dispatch($review);

            return $review;
        });
    }
}
