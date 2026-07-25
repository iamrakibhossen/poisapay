<?php

declare(strict_types=1);

namespace App\Sell\Events;

/** A buyer opened a refund request. Audited. */
class RefundRequested extends RefundRequestEvent
{
    public function auditAction(): string
    {
        return 'refund.requested';
    }
}
