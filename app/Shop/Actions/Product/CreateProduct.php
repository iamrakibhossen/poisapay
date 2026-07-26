<?php

declare(strict_types=1);

namespace App\Shop\Actions\Product;

use App\Shop\DTOs\ProductData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Events\ProductSaved;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;
use App\Shop\Services\CatalogService;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly SyncVariants $syncVariants,
    ) {}

    public function execute(Seller $seller, ProductData $data): Product
    {
        if (! $seller->canSell()) {
            throw ShopException::notApproved();
        }

        return DB::transaction(function () use ($seller, $data): Product {
            $product = $seller->products()->create([
                'type' => $data->type,
                'name' => $data->name,
                'slug' => $this->catalog->uniqueSlug($seller, $data->name),
                'summary' => $data->summary,
                'description' => $data->description,
                'image' => $data->image,
                'status' => ProductStatus::Draft,
                'price_amount' => $data->priceAmount,
                'price_asset_id' => $data->priceAssetId,
                'compare_price_amount' => $data->comparePriceAmount,
                'requires_shipping' => $data->requiresShipping,
                'attributes' => $data->attributes,
            ]);

            if ($data->variants !== []) {
                $this->syncVariants->execute($product, $data->variants);
            }

            ProductSaved::dispatch($product->refresh(), true);

            return $product;
        });
    }
}
