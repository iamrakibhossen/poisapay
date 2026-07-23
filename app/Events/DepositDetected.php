<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted when an inbound deposit is first observed on-chain (still pending confirmations). */
class DepositDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $depositId) {}
}
