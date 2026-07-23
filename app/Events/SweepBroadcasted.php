<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted after a sweep transfer is signed and broadcast (ledger not yet touched). */
class SweepBroadcasted
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $sweepId) {}
}
