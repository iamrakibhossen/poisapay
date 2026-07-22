<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Transfer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $transferId) {}

    public function broadcastOn(): array
    {
        $transfer = Transfer::find($this->transferId);
        if (! $transfer) {
            return [];
        }

        return array_values(array_filter([
            new PrivateChannel("user.{$transfer->sender_id}"),
            $transfer->recipient_id ? new PrivateChannel("user.{$transfer->recipient_id}") : null,
        ]));
    }

    public function broadcastAs(): string
    {
        return 'transfer.completed';
    }
}
