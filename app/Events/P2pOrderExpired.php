<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** The payment window elapsed; the order expired and escrow was refunded. */
class P2pOrderExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $orderId) {}
}
