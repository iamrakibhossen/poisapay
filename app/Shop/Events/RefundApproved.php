<?php

declare(strict_types=1);

namespace App\Shop\Events;

/** A refund request was approved and the money returned to the buyer. Audited. */
class RefundApproved extends RefundRequestEvent
{
    public function auditAction(): string
    {
        return 'refund.approved';
    }
}
