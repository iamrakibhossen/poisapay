<?php

declare(strict_types=1);

namespace App\Shop\Http\Resources;

use App\Shop\Models\RefundRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RefundRequest
 */
class RefundRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order->number),
            'type' => $this->type,
            'status' => $this->status->value,
            'amount_requested' => (int) $this->amount_requested,
            'amount_refunded' => $this->amount_refunded !== null ? (int) $this->amount_refunded : null,
            'reason' => $this->reason,
            'resolver_type' => $this->resolver_type,
            'resolution_note' => $this->resolution_note,
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'escalated_at' => $this->escalated_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
