<?php

declare(strict_types=1);

namespace App\Sell\Actions\Product;

use App\Sell\DTOs\VariantData;
use App\Sell\Models\Product;
use App\Sell\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Reconcile a product's variants against the given set: update-by-id, create the
 * new, deactivate the removed (never hard-delete — a variant may be referenced by
 * historical order items). Sets the product's `has_variants` flag.
 */
class SyncVariants
{
    /**
     * @param  list<VariantData>  $variants
     */
    public function execute(Product $product, array $variants): void
    {
        DB::transaction(function () use ($product, $variants) {
            $keepIds = [];

            foreach ($variants as $i => $v) {
                $attrs = [
                    'options' => $v->options,
                    'price_amount' => $v->priceAmount,
                    'stock' => $v->stock,
                    'sku' => $v->sku,
                    'weight_grams' => $v->weightGrams,
                    'position' => $i,
                    'is_active' => true,
                ];

                $model = $v->id
                    ? $product->variants()->whereKey($v->id)->first()
                    : null;

                if ($model) {
                    $model->update($attrs);
                } else {
                    $model = $product->variants()->create($attrs);
                }
                $keepIds[] = $model->getKey();
            }

            // Deactivate variants no longer in the set (preserve for order history).
            $product->variants()->whereKeyNot($keepIds)->update(['is_active' => false]);

            $product->update(['has_variants' => count($keepIds) > 0]);
        });
    }
}
