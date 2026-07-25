<?php

declare(strict_types=1);

namespace App\Sell\Events;

use App\Sell\Enums\OrderStatus;
use App\Sell\Models\Order;
use Illuminate\Database\Eloquent\Model;

/** An order moved through fulfilment (paid → processing → shipped → …). Audited. */
class OrderStatusChanged extends SellDomainEvent
{
    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {}

    public function auditAction(): string
    {
        return 'order.'.$this->to->value;
    }

    public function auditSubject(): ?Model
    {
        return $this->order;
    }

    public function auditData(): array
    {
        return ['number' => $this->order->number, 'from' => $this->from->value, 'to' => $this->to->value];
    }
}
