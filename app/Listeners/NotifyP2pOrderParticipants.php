<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\P2pBuyerMarkedPaid;
use App\Events\P2pOrderCancelled;
use App\Events\P2pOrderCompleted;
use App\Events\P2pOrderCreated;
use App\Events\P2pOrderDisputed;
use App\Events\P2pOrderExpired;
use App\Models\P2pOrder;
use App\Models\User;
use App\Notifications\LedgerEventNotification;

/**
 * Notifies the trade participants (in-app + email) on every P2P order state change,
 * so buyer/seller are alerted even when they aren't watching the chat. Subscribed in
 * {@see \App\Providers\P2pServiceProvider} alongside {@see P2pChatSubscriber}.
 */
class NotifyP2pOrderParticipants
{
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
        if (! $o = $this->order($event->orderId)) {
            return;
        }
        // A buyer opened an order against the seller's ad → alert the seller.
        $this->notify($o->seller, 'New P2P order',
            "A buyer opened order {$o->ref} for {$o->cryptoMoney()->format()} ({$this->fiat($o)}). Your escrow is locked — await their payment.",
            'p2p.order.created', $o);
    }

    public function onBuyerPaid(P2pBuyerMarkedPaid $event): void
    {
        if (! $o = $this->order($event->orderId)) {
            return;
        }
        $this->notify($o->seller, 'Buyer marked paid',
            "The buyer marked payment sent on order {$o->ref}. Confirm receipt and release the crypto.",
            'p2p.order.paid', $o);
    }

    public function onCompleted(P2pOrderCompleted $event): void
    {
        if (! $o = $this->order($event->orderId)) {
            return;
        }
        $this->notify($o->buyer, 'Crypto released',
            "Order {$o->ref} is complete — {$o->netMoney()->format()} was released to your wallet.",
            'p2p.order.completed', $o);
    }

    public function onCancelled(P2pOrderCancelled $event): void
    {
        if (! $o = $this->order($event->orderId)) {
            return;
        }
        $this->both($o, 'P2P order cancelled',
            "Order {$o->ref} was cancelled — the escrow was refunded to the seller.",
            'p2p.order.cancelled');
    }

    public function onExpired(P2pOrderExpired $event): void
    {
        if (! $o = $this->order($event->orderId)) {
            return;
        }
        $this->both($o, 'P2P order expired',
            "Order {$o->ref} expired — the payment window elapsed and the escrow was refunded to the seller.",
            'p2p.order.expired');
    }

    public function onDisputed(P2pOrderDisputed $event): void
    {
        if (! $o = $this->order($event->orderId)) {
            return;
        }
        $this->both($o, 'P2P order disputed',
            "A dispute was opened on order {$o->ref}. An operator will review the evidence and rule.",
            'p2p.order.disputed');
    }

    private function order(string $id): ?P2pOrder
    {
        return P2pOrder::with(['buyer', 'seller', 'asset'])->find($id);
    }

    /** Notify both parties (used when either side may have triggered the change). */
    private function both(P2pOrder $order, string $title, string $body, string $event): void
    {
        $this->notify($order->buyer, $title, $body, $event, $order);
        $this->notify($order->seller, $title, $body, $event, $order);
    }

    private function notify(?User $user, string $title, string $body, string $event, P2pOrder $order): void
    {
        $user?->notify(new LedgerEventNotification($title, $body, $event, route('p2p.order', $order)));
    }

    private function fiat(P2pOrder $order): string
    {
        return number_format((float) $order->fiat_amount, 2).' '.$order->fiat_currency;
    }
}
