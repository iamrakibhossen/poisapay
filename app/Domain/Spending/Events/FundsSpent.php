<?php

declare(strict_types=1);

namespace App\Domain\Spending\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted after the Spending Engine settles a spend. Carries only ids so
 * listeners (notifications, webhooks, analytics) can hydrate what they need
 * without coupling to the engine internals.
 */
class FundsSpent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $userId,
        public string $entryId,
        public string $purpose,
    ) {}
}
