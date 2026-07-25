<?php

declare(strict_types=1);

namespace App\Sell\Events;

use App\Sell\Contracts\AuditableEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for all Sell domain events. Being an {@see AuditableEvent}, every
 * concrete subclass is automatically audited (see AuditSellEvent + SellServiceProvider)
 * and can be listened to by any number of decoupled listeners.
 *
 * Subclasses typically set a subject and override auditData(); the sensible
 * defaults here keep trivial events to a one-line class.
 */
abstract class SellDomainEvent implements AuditableEvent
{
    use Dispatchable;
    use SerializesModels;

    abstract public function auditAction(): string;

    public function auditSubject(): ?Model
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function auditData(): array
    {
        return [];
    }

    public function auditActor(): ?Model
    {
        return null;
    }
}
