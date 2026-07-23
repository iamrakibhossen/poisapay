<?php

declare(strict_types=1);

use App\Models\P2pOrder;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/** A user may only listen on their own private channel (§9.2 authz). */
Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});

/** P2P order chat room — only the two counterparties may subscribe (and whisper). */
Broadcast::channel('p2p.order.{orderId}', function (User $user, string $orderId) {
    $order = P2pOrder::find($orderId);

    return $order && in_array($user->id, [$order->buyer_id, $order->seller_id], true);
});

Broadcast::channel('App.Models.User.{id}', function (User $user, string $id) {
    return $user->id === $id;
});
