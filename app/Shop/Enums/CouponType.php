<?php

declare(strict_types=1);

namespace App\Shop\Enums;

/** How a coupon's `value` is interpreted. */
enum CouponType: string
{
    case Percent = 'percent'; // value = basis points (1000 = 10%)
    case Fixed = 'fixed';     // value = minor units off

    public function label(): string
    {
        return $this === self::Percent ? 'Percentage' : 'Fixed amount';
    }
}
