<?php

declare(strict_types=1);

namespace App\Sell\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * A Sell domain event that must be recorded to the platform audit trail.
 *
 * Every state-changing thing in the Sell module fires an event implementing this
 * contract; a single listener records it through the core Audit module — so
 * "audit everything" is guaranteed by construction, not by remembering to call a
 * logger at each call site.
 */
interface AuditableEvent
{
    /** Dot-cased action, e.g. "product.created" (recorded as "sell.product.created"). */
    public function auditAction(): string;

    /** The model this event concerns (audit subject), if any. */
    public function auditSubject(): ?Model;

    /**
     * Extra properties stored on the audit row (before/after, ids, amounts…).
     *
     * @return array<string, mixed>
     */
    public function auditData(): array;

    /** Explicit actor; null lets the logger resolve the current admin/user guard. */
    public function auditActor(): ?Model;
}
