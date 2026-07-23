<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** The buyer attested they sent the fiat payment. */
class P2pBuyerMarkedPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $orderId) {}
}
