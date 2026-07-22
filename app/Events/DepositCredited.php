<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Deposit;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted after a deposit is credited (TDD §6.1 step 7 — Reverb push). */
class DepositCredited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $depositId) {}

    public function broadcastOn(): array
    {
        $deposit = Deposit::find($this->depositId);

        return $deposit ? [new PrivateChannel("user.{$deposit->user_id}")] : [new Channel('deposits')];
    }

    public function broadcastAs(): string
    {
        return 'deposit.credited';
    }
}
