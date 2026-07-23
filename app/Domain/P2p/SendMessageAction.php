<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Enums\P2pMessageType;
use App\Models\P2pOrder;
use App\Models\P2pOrderMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * A counterparty posts a chat message. Attachments (image/receipt) are stored
 * on the private `local` disk under the order's folder and only ever served via
 * an authorised download route.
 */
class SendMessageAction
{
    public function __construct(private readonly P2pChatService $chat) {}

    public function execute(
        P2pOrder $order,
        User $sender,
        P2pMessageType $type,
        ?string $body = null,
        ?UploadedFile $file = null,
    ): P2pOrderMessage {
        if (! in_array($sender->getKey(), [$order->buyer_id, $order->seller_id], true)) {
            throw new RuntimeException('You are not a party to this order.');
        }

        if ($type === P2pMessageType::System) {
            throw new RuntimeException('System messages cannot be sent manually.');
        }

        $path = null;
        if ($type->hasAttachment()) {
            if (! $file) {
                throw new RuntimeException('An attachment is required for this message type.');
            }
            $path = $file->store("p2p-chat/{$order->id}", 'local');
        }

        if ($path === null && ($body === null || trim($body) === '')) {
            throw new RuntimeException('Message cannot be empty.');
        }

        return $this->chat->record($order, 'user', (string) $sender->getKey(), $type, $body, $path);
    }
}
