<?php

declare(strict_types=1);

namespace App\Sell\Listeners;

use App\Sell\Contracts\AuditableEvent;
use App\Sell\Support\SellAudit;

/**
 * Records every {@see AuditableEvent} to the platform audit trail. Registered
 * against the interface in SellServiceProvider, so it catches all Sell domain
 * events without per-event wiring. Runs synchronously and cannot block the
 * business op (the underlying ActivityLogger swallows its own failures).
 */
class AuditSellEvent
{
    public function handle(AuditableEvent $event): void
    {
        SellAudit::log(
            $event->auditAction(),
            $event->auditSubject(),
            $event->auditData(),
            actor: $event->auditActor(),
        );
    }
}
