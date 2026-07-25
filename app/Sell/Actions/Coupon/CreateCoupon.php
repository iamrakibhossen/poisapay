<?php

declare(strict_types=1);

namespace App\Sell\Actions\Coupon;

use App\Sell\Enums\CouponType;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Coupon;
use App\Sell\Models\Seller;

/**
 * Create a discount code for a seller. Codes are unique per seller (case-insensitive);
 * a percent value arrives as whole percent (converted to basis points), a fixed
 * value as minor units.
 */
class CreateCoupon
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Seller $seller, array $data): Coupon
    {
        $code = mb_strtoupper(trim((string) $data['code']));
        $type = CouponType::from($data['type']);

        $exists = Coupon::withTrashed()
            ->where('seller_id', $seller->getKey())
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->exists();
        if ($exists) {
            throw SellException::invalidCoupon('You already have a coupon with that code.');
        }

        return Coupon::create([
            'seller_id' => $seller->getKey(),
            'product_id' => $data['product_id'] ?? null,
            'code' => $code,
            'type' => $type,
            // percent → basis points; fixed → minor units (already scaled by caller)
            'value' => $type === CouponType::Percent ? (int) round($data['value'] * 100) : (int) $data['value'],
            'min_order_amount' => $data['min_order_amount'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_customer_limit' => $data['per_customer_limit'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
