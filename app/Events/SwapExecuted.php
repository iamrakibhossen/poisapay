<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A user-initiated swap completed. Carries the conversion id only (listeners
 * re-fetch) — the extension point for notifications, analytics, or webhooks.
 */
class SwapExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $conversionId) {}
}
