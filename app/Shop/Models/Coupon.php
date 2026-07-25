<?php

declare(strict_types=1);

namespace App\Shop\Models;

use App\Shop\Enums\CouponType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A seller discount code. Scoped to one product (product_id) or seller-wide
 * (null). `value` is basis points for percent coupons, minor units for fixed.
 */
class Coupon extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'shop_coupons';

    protected $fillable = [
        'seller_id', 'product_id', 'code', 'type', 'value', 'min_order_amount',
        'usage_limit', 'used_count', 'per_customer_limit', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'min_order_amount' => 'integer',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'per_customer_limit' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
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

    /** The discount (minor units) this coupon yields on a line total, capped at it. */
    public function discountFor(int $lineTotal): int
    {
        $off = $this->type === CouponType::Percent
            ? intdiv($lineTotal * $this->value, 10_000)
            : $this->value;

        return max(0, min($off, $lineTotal));
    }
}
