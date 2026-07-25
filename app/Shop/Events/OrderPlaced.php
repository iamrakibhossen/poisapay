<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Shop\Models\Order;
use Illuminate\Database\Eloquent\Model;

/**
 * A paid order was created. Auto-audited (`sell.order.placed`); listeners fan out
 * to receipts/notifications, analytics, and (later) fulfilment jobs.
 */
class OrderPlaced extends ShopDomainEvent
{
    public function __construct(public readonly Order $order) {}

    public function auditAction(): string
    {
        return 'order.placed';
    }

    public function auditSubject(): ?Model
    {
        return $this->order;
    }

    public function auditData(): array
    {
        return [
            'number' => $this->order->number,
            'total' => (int) $this->order->total_amount,
            'commission' => (int) $this->order->commission_amount,
            'ledger_entry_id' => $this->order->ledger_entry_id,
        ];
    }
}
