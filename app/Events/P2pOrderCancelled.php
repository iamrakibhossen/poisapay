<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** An order was cancelled before payment; the seller's escrow was refunded. */
class P2pOrderCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $orderId) {}
}
