<?php

declare(strict_types=1);

namespace App\Sell\Events;

use App\Sell\Models\Message;
use App\Sell\Models\Order;
use Illuminate\Database\Eloquent\Model;

/** A message was posted to an order's shared conversation. Audited. */
class MessageSent extends SellDomainEvent
{
    public function __construct(
        public readonly Order $order,
        public readonly Message $message,
    ) {}

    public function auditAction(): string
    {
        return 'message.sent';
    }

    public function auditSubject(): ?Model
    {
        return $this->order;
    }

    public function auditData(): array
    {
        return ['order' => $this->order->number, 'author' => $this->message->author_type];
    }
}
