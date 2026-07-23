<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted when a sweep broadcast is rejected or the on-chain transfer reverts (ledger untouched). */
class SweepFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $sweepId, public string $reason) {}
}
