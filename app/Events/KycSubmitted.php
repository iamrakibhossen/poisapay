<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Emitted after a user submits a KYC profile for review (TDD §10.1). */
class KycSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $profileId) {}
}
