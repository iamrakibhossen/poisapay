<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Models\P2pOrder;
use App\Models\User;

/** Mark every message the viewer did not author as read. Returns rows updated. */
class MarkMessagesReadAction
{
    public function execute(P2pOrder $order, User $viewer): int
    {
        return $order->messages()
            ->whereNull('read_at')
            ->where(function ($query) use ($viewer) {
                $query->where('sender_type', '!=', 'user')
                    ->orWhere('sender_id', '!=', $viewer->getKey());
            })
            ->update(['read_at' => now()]);
    }
}
