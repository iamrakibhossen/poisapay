<?php

declare(strict_types=1);

namespace App\Sell\Http\Resources;

use App\Sell\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Seller
 */
class SellerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'brand_name' => $this->brand_name,
            'bio' => $this->bio,
            'website' => $this->website,
            'country' => $this->country,
            'categories' => $this->categories,
            'status' => $this->status->value,
            'plan' => $this->plan,
            'can_sell' => $this->canSell(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
