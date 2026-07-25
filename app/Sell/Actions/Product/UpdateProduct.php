<?php

declare(strict_types=1);

namespace App\Sell\Actions\Product;

use App\Sell\DTOs\ProductData;
use App\Sell\Events\ProductSaved;
use App\Sell\Models\Product;
use Illuminate\Support\Facades\DB;

/** Update a product's fields + variants. Slug is stable (never re-generated, so
 *  existing links/domains keep working). */
class UpdateProduct
{
    public function __construct(private readonly SyncVariants $syncVariants) {}

    public function execute(Product $product, ProductData $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $product->update([
                'type' => $data->type,
                'name' => $data->name,
                'summary' => $data->summary,
                'description' => $data->description,
                'price_amount' => $data->priceAmount,
                'price_asset_id' => $data->priceAssetId,
                'compare_price_amount' => $data->comparePriceAmount,
                'requires_shipping' => $data->requiresShipping,
                'attributes' => $data->attributes,
            ]);

            // Only reconcile variants when the caller actually manages them —
            // an empty set means "left untouched", not "deactivate all".
            if ($data->variants !== []) {
                $this->syncVariants->execute($product, $data->variants);
            }

            ProductSaved::dispatch($product->refresh(), false);

            return $product;
        });
    }
}
