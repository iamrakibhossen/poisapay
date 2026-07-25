<?php

declare(strict_types=1);

namespace App\Sell\Http\Resources;

use App\Sell\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'status' => $this->status->value,
            'price' => [
                'amount' => (int) $this->price_amount,
                'compare' => $this->compare_price_amount !== null ? (int) $this->compare_price_amount : null,
                'asset_id' => (int) $this->price_asset_id,
            ],
            'has_variants' => $this->has_variants,
            'requires_shipping' => $this->requires_shipping,
            'attributes' => $this->attributes,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'sales_count' => (int) $this->sales_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
