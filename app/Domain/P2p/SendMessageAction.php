<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Enums\P2pMessageType;
use App\Models\P2pOrder;
use App\Models\P2pOrderMessage;
use App\Models\User;
use App\Notifications\P2pChatMessageNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
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

        $message = $this->chat->record($order, 'user', (string) $sender->getKey(), $type, $body, $path);

        $this->notifyCounterparty($order, $sender, $type, $body);

        return $message;
    }

    /** Alert the other party (in-app) so they see the reply even when off the page. */
    private function notifyCounterparty(P2pOrder $order, User $sender, P2pMessageType $type, ?string $body): void
    {
        $counterpartyId = $sender->getKey() === $order->buyer_id ? $order->seller_id : $order->buyer_id;
        $counterparty = User::find($counterpartyId);
        if (! $counterparty) {
            return;
        }

        $preview = $type->hasAttachment()
            ? __('Sent an attachment')
            : Str::limit(trim((string) $body), 100);

        $counterparty->notify(new P2pChatMessageNotification(
            __('New message on order :ref', ['ref' => $order->ref]),
            $sender->name.': '.$preview,
            route('p2p.order', $order),
        ));
    }
}
