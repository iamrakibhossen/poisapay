<?php

declare(strict_types=1);

namespace App\Shop\Http\Resources;

use App\Shop\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'options' => $this->options,
            'sku' => $this->sku,
            'price_amount' => $this->price_amount !== null ? (int) $this->price_amount : null,
            'stock' => $this->stock,
            'weight_grams' => $this->weight_grams,
            'is_active' => $this->is_active,
        ];
    }
}
