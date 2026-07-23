<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Escrow was released to the buyer — the trade completed. */
class P2pOrderCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $orderId) {}
}
