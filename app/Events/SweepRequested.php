<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted when a sweep of a deposit address into the hot wallet is requested (before broadcast). */
class SweepRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $depositAddressId, public int $assetId) {}
}
