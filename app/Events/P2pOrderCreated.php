<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A P2P order was opened and the seller's USDT locked in escrow. */
class P2pOrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $orderId) {}
}
