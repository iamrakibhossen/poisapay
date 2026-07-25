<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Shop\Models\Message;
use App\Shop\Models\Order;
use Illuminate\Database\Eloquent\Model;

/** A message was posted to an order's shared conversation. Audited. */
class MessageSent extends ShopDomainEvent
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
