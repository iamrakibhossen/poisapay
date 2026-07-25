<?php

declare(strict_types=1);

namespace App\Sell\Events;

/** A refund request was rejected by the seller or an operator. Audited. */
class RefundRejected extends RefundRequestEvent
{
    public function auditAction(): string
    {
        return 'refund.rejected';
    }
}
