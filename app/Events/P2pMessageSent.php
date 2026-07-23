<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\P2pOrderMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A chat message was posted to an order thread. Broadcasts live to the shared
 * private channel both counterparties subscribe to (also used for typing
 * whispers). Attachments are fetched over an authorised download route, never
 * embedded in the payload.
 */
class P2pMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $orderId, public string $messageId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("p2p.order.{$this->orderId}")];
    }

    public function broadcastAs(): string
    {
        return 'p2p.message';
    }

    public function broadcastWith(): array
    {
        $message = P2pOrderMessage::find($this->messageId);
        if (! $message) {
            return [];
        }

        return [
            'id' => $message->id,
            'order_id' => $message->order_id,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'type' => $message->type->value,
            'body' => $message->body,
            'has_attachment' => $message->attachment_path !== null,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
