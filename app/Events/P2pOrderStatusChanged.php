<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A P2P order changed state. Broadcasts live on the same private channel the
 * two counterparties already share for chat, so the order page can refresh its
 * stage, timer and actions without a manual reload. Deferred until the enclosing
 * transaction commits so a rolled-back transition is never announced.
 */
class P2pOrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public string $orderId, public string $status) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("p2p.order.{$this->orderId}")];
    }

    public function broadcastAs(): string
    {
        return 'p2p.status';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId, 'status' => $this->status];
    }
}
