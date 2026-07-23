<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A counterparty posted a chat message on a P2P order. In-app only (no email) —
 * a live thread can be chatty, so we surface it in the bell without flooding the
 * inbox. Order lifecycle events keep their in-app + email {@see LedgerEventNotification}.
 */
class P2pChatMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $url,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'product',
            'event' => 'p2p.order.message',
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
