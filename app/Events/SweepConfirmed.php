<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted after a sweep confirms on-chain and its treasury ledger entry is posted. */
class SweepConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $sweepId) {}
}
