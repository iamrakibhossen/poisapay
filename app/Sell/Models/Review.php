<?php

declare(strict_types=1);

namespace App\Sell\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's rating of a purchased product. The order_id proves the purchase, and
 * a unique (order_id, product_id) enforces one review per purchase. The seller
 * may reply once.
 */
class Review extends Model
{
    use HasUuids;

    protected $table = 'sell_reviews';

    protected $fillable = [
        'seller_id', 'product_id', 'order_id', 'buyer_user_id',
        'rating', 'title', 'body', 'media', 'status', 'seller_reply', 'seller_replied_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'media' => 'array',
            'seller_replied_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }
}
