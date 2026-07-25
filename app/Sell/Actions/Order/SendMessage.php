<?php

declare(strict_types=1);

namespace App\Sell\Actions\Order;

use App\Sell\Events\MessageSent;
use App\Sell\Models\Message;
use App\Sell\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Post a message to an order's shared conversation. There is no thread table —
 * the order IS the conversation. Ordering + unread state live on the order row
 * (last_message_at + seller_unread/buyer_unread): the sender's side is marked
 * read, the counterparty's is flagged unread.
 */
class SendMessage
{
    public function execute(Order $order, string $authorType, ?string $authorId, string $body): Message
    {
        return DB::transaction(function () use ($order, $authorType, $authorId, $body): Message {
            $message = $order->messages()->create([
                'author_type' => $authorType,
                'author_id' => $authorId,
                'body' => $body,
            ]);

            $order->forceFill([
                'last_message_at' => now(),
                'seller_unread' => $authorType === 'seller' ? false : true,
                'buyer_unread' => $authorType === 'buyer' ? false : true,
            ])->save();

            MessageSent::dispatch($order, $message);

            return $message;
        });
    }
}
