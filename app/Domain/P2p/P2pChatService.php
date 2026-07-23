<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Enums\P2pMessageType;
use App\Events\P2pMessageSent;
use App\Models\P2pOrder;
use App\Models\P2pOrderMessage;

/**
 * Persists order-chat messages and broadcasts them live to the order room.
 * Used by {@see SendMessageAction} (user messages) and the engine for system
 * messages on state changes.
 */
class P2pChatService
{
    public function record(
        P2pOrder $order,
        string $senderType,
        ?string $senderId,
        P2pMessageType $type,
        ?string $body,
        ?string $attachmentPath = null,
    ): P2pOrderMessage {
        $message = P2pOrderMessage::create([
            'order_id' => $order->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'type' => $type,
            'body' => $body,
            'attachment_path' => $attachmentPath,
        ]);

        P2pMessageSent::dispatch($order->id, $message->id);

        return $message;
    }

    /** Engine-generated system message announcing a state change. */
    public function system(P2pOrder $order, string $body): P2pOrderMessage
    {
        return $this->record($order, 'system', null, P2pMessageType::System, $body);
    }
}
