<?php

declare(strict_types=1);

namespace App\Sell\Actions\Product;

use App\Sell\Enums\ProductStatus;
use App\Sell\Events\ProductStatusChanged;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Product;

/** Publish / archive / unpublish a product, with publishability guards. */
class SetProductStatus
{
    public function execute(Product $product, ProductStatus $to): Product
    {
        $from = $product->status;

        if ($to === ProductStatus::Published) {
            $this->assertPublishable($product);
        }

        $product->update([
            'status' => $to,
            'published_at' => $to === ProductStatus::Published && ! $product->published_at
                ? now()
                : $product->published_at,
        ]);

        ProductStatusChanged::dispatch($product, $from, $to);

        return $product->refresh();
    }

    private function assertPublishable(Product $product): void
    {
        if ($product->price_amount < 0) {
            throw SellException::notPublishable('the price is invalid');
        }

        // A variant product must have at least one active variant to sell.
        if ($product->has_variants && $product->variants()->where('is_active', true)->doesntExist()) {
            throw SellException::notPublishable('add at least one variant');
        }
    }
}
