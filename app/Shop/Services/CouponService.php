<?php

declare(strict_types=1);

namespace App\Shop\Services;

use App\Models\User;
use App\Shop\Enums\OrderStatus;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\Coupon;
use App\Shop\Models\Order;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;

/**
 * Validates discount codes at checkout. A coupon must belong to the seller, be
 * active, in its date window, within scope (seller-wide or this product), meet
 * the minimum order, and not be over its usage / per-customer limits.
 */
class CouponService
{
    /** Resolve + fully validate a code, throwing ShopException on any failure. */
    public function validate(Seller $seller, Product $product, string $code, int $lineTotal, User $buyer): Coupon
    {
        $coupon = Coupon::query()
            ->where('seller_id', $seller->getKey())
            ->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])
            ->first();

        if (! $coupon || ! $coupon->is_active) {
            throw ShopException::invalidCoupon();
        }
        if ($coupon->product_id !== null && $coupon->product_id !== $product->getKey()) {
            throw ShopException::invalidCoupon('This code does not apply to this product.');
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw ShopException::invalidCoupon('This code is not active yet.');
        }
        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            throw ShopException::invalidCoupon('This code has expired.');
        }
        if ($coupon->min_order_amount !== null && $lineTotal < $coupon->min_order_amount) {
            throw ShopException::invalidCoupon('Order total is below this code’s minimum.');
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw ShopException::invalidCoupon('This code has reached its usage limit.');
        }
        if ($coupon->per_customer_limit !== null) {
            $used = Order::where('coupon_id', $coupon->getKey())
                ->where('buyer_user_id', $buyer->getKey())
                ->whereNotIn('status', [OrderStatus::Cancelled->value])
                ->count();
            if ($used >= $coupon->per_customer_limit) {
                throw ShopException::invalidCoupon('You have already used this code.');
            }
        }
        if ($coupon->discountFor($lineTotal) <= 0) {
            throw ShopException::invalidCoupon();
        }

        return $coupon;
    }

    /** Best-effort resolve for display (pay page preview): returns null if invalid. */
    public function preview(Seller $seller, Product $product, ?string $code, int $lineTotal, User $buyer): ?Coupon
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        try {
            return $this->validate($seller, $product, $code, $lineTotal, $buyer);
        } catch (ShopException) {
            return null;
        }
    }
}
