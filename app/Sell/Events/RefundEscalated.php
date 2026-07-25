<?php

declare(strict_types=1);

namespace App\Sell\Events;

/** A refund request was escalated to operators (by the buyer or the SLA job). Audited. */
class RefundEscalated extends RefundRequestEvent
{
    public function auditAction(): string
    {
        return 'refund.escalated';
    }
}
