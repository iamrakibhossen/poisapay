<?php

declare(strict_types=1);

namespace App\Sell\Services;

use App\Sell\Models\Product;
use App\Sell\Models\ProductVariant;
use App\Sell\Models\Seller;

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
}
