<?php

declare(strict_types=1);

namespace App\Shop\Http\Resources;

use App\Shop\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'amounts' => [
                'subtotal' => (int) $this->subtotal_amount,
                'discount' => (int) $this->discount_amount,
                'tax' => (int) $this->tax_amount,
                'shipping' => (int) $this->shipping_amount,
                'total' => (int) $this->total_amount,
                'commission' => (int) $this->commission_amount,
                'seller_net' => (int) $this->seller_net_amount,
                'asset_id' => (int) $this->asset_id,
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'name' => $i->name_snapshot,
                'kind' => $i->kind->value,
                'quantity' => $i->quantity,
                'line_total' => (int) $i->line_total_amount,
            ])),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
