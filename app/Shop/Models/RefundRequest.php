<?php

declare(strict_types=1);

namespace App\Shop\Models;

use App\Models\User;
use App\Shop\Actions\Order\RefundOrder;
use App\Shop\Enums\RefundRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's request to refund a paid order (full or partial). Approving it posts a
 * balanced ledger reversal via {@see RefundOrder}.
 *
 * @property RefundRequestStatus $status
 */
class RefundRequest extends Model
{
    use HasUuids;

    protected $table = 'sell_refund_requests';

    protected $fillable = [
        'order_id', 'seller_id', 'buyer_user_id', 'type',
        'amount_requested', 'amount_refunded', 'reason', 'status',
        'resolver_type', 'resolver_id', 'resolution_note', 'ledger_entry_id',
        'sla_due_at', 'escalated_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RefundRequestStatus::class,
            'amount_requested' => 'integer',
            'amount_refunded' => 'integer',
            'sla_due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    /** Amount still refundable on the order (captured total minus what's already been refunded). */
    public static function remainingRefundable(Order $order): int
    {
        $captured = (int) $order->seller_net_amount + (int) $order->commission_amount;

        return max(0, $captured - (int) ($order->refunded_amount ?? 0));
    }
}
