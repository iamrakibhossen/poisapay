<?php

declare(strict_types=1);

namespace App\Shop\Services;

use App\Shop\Models\Product;
use App\Shop\Models\ProductVariant;
use App\Shop\Models\Seller;
use App\Support\Money;

/**
 * Computes the money split for an order line in integer minor units — the amounts
 * the Ledger will actually move. Commission is the platform's cut; net is the
 * seller's. All integer math (no floats).
 *
 * @phpstan-type PriceBreakdown array{unit:int, line_total:int, commission:int, net:int}
 */
class PricingService
{
    /** @return PriceBreakdown */
    public function line(Product $product, ?ProductVariant $variant, int $quantity, Seller $seller): array
    {
        $unit = $variant?->price_amount ?? (int) $product->price_amount;
        $lineTotal = $unit * max(1, $quantity);
        $commission = intdiv($lineTotal * $seller->commissionBps(), 10_000);
        $net = $lineTotal - $commission;

        return ['unit' => $unit, 'line_total' => $lineTotal, 'commission' => $commission, 'net' => $net];
    }

    /**
     * Flat shipping fee for a physical product (its `shipping_fee` attribute, a
     * decimal) in the asset's integer minor units. Zero when not shipped / unset.
     */
    public function shippingFee(Product $product): int
    {
        if (! $product->requires_shipping) {
            return 0;
        }

        $fee = $product->attributes['shipping_fee'] ?? null;
        if ($fee === null || $fee === '' || (float) $fee <= 0) {
            return 0;
        }

        $asset = $product->priceAsset;

        return (int) Money::ofDecimal((string) $fee, $asset->decimals, $asset->symbol)->baseString();
    }
}
