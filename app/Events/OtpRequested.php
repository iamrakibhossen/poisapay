<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OtpRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $identifier,
        public string $channel,
        public string $code,
        public string $purpose,
    ) {}
}
