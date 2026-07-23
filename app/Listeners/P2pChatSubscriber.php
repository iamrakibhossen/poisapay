<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\P2p\P2pChatService;
use App\Events\P2pBuyerMarkedPaid;
use App\Events\P2pOrderCancelled;
use App\Events\P2pOrderCompleted;
use App\Events\P2pOrderCreated;
use App\Events\P2pOrderDisputed;
use App\Events\P2pOrderExpired;
use App\Models\P2pOrder;
use App\Providers\P2pServiceProvider;

/**
 * Posts an in-thread system message whenever an order changes state, so both
 * parties always see the trade's progress in the chat. Decoupled from the
 * engine — subscribed in {@see P2pServiceProvider}.
 */
class P2pChatSubscriber
{
    public function __construct(private readonly P2pChatService $chat) {}

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            P2pOrderCreated::class => 'onCreated',
            P2pBuyerMarkedPaid::class => 'onBuyerPaid',
            P2pOrderCompleted::class => 'onCompleted',
            P2pOrderCancelled::class => 'onCancelled',
            P2pOrderExpired::class => 'onExpired',
            P2pOrderDisputed::class => 'onDisputed',
        ];
    }

    public function onCreated(P2pOrderCreated $event): void
    {
        $this->say($event->orderId, 'Order opened — the seller\'s USDT is locked in escrow. Buyer, please pay within the time limit and mark the payment as sent.');
    }

    public function onBuyerPaid(P2pBuyerMarkedPaid $event): void
    {
        $this->say($event->orderId, 'The buyer marked the payment as sent. Seller, please confirm receipt and release the escrow.');
    }

    public function onCompleted(P2pOrderCompleted $event): void
    {
        $this->say($event->orderId, 'Escrow released to the buyer — trade completed.');
    }

    public function onCancelled(P2pOrderCancelled $event): void
    {
        $this->say($event->orderId, 'Order cancelled — the escrow was refunded to the seller.');
    }

    public function onExpired(P2pOrderExpired $event): void
    {
        $this->say($event->orderId, 'Order expired — the payment window elapsed and the escrow was refunded to the seller.');
    }

    public function onDisputed(P2pOrderDisputed $event): void
    {
        $this->say($event->orderId, 'A dispute was opened on this order. An operator will review the evidence and rule.');
    }

    private function say(string $orderId, string $body): void
    {
        $order = P2pOrder::find($orderId);
        if ($order) {
            $this->chat->system($order, $body);
        }
    }
}
