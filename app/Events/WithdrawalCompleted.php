<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Withdrawal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WithdrawalCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $withdrawalId) {}

    public function broadcastOn(): array
    {
        $withdrawal = Withdrawal::find($this->withdrawalId);

        return $withdrawal ? [new PrivateChannel("user.{$withdrawal->user_id}")] : [];
    }

    public function broadcastAs(): string
    {
        return 'withdrawal.completed';
    }
}
