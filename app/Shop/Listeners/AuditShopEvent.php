<?php

declare(strict_types=1);

namespace App\Shop\Listeners;

use App\Shop\Contracts\AuditableEvent;
use App\Shop\Support\ShopAudit;

/**
 * Records every {@see AuditableEvent} to the platform audit trail. Registered
 * against the interface in ShopServiceProvider, so it catches all Sell domain
 * events without per-event wiring. Runs synchronously and cannot block the
 * business op (the underlying ActivityLogger swallows its own failures).
 */
class AuditShopEvent
{
    public function handle(AuditableEvent $event): void
    {
        ShopAudit::log(
            $event->auditAction(),
            $event->auditSubject(),
            $event->auditData(),
            actor: $event->auditActor(),
        );
    }
}
