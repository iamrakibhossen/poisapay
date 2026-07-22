<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted after a KYC profile is rejected (TDD §10.1). */
class KycRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $userId, public string $reason) {}
}
