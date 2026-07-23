<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A party opened a dispute; escrow stays locked pending an operator ruling. */
class P2pOrderDisputed
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $orderId, public string $disputeId) {}
}
