<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted after a KYC profile is approved and the user's tier is upgraded (TDD §10.1). */
class KycApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $userId, public string $tier) {}
}
