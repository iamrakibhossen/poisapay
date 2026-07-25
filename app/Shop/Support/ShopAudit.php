<?php

declare(strict_types=1);

namespace App\Shop\Support;

use App\Domain\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Sell-scoped audit seam. Delegates to the core Audit module ({@see ActivityLogger})
 * — Sell never owns its own audit storage — and namespaces every action under
 * `sell.` so the whole module's trail is filterable with `action LIKE 'shop.%'`.
 *
 * Prefer firing a {@see \App\Shop\Events\ShopDomainEvent}; call this directly only
 * for non-event side-audits (e.g. a read of sensitive data).
 */
final class ShopAudit
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        array $data = [],
        ?string $description = null,
        ?Model $actor = null,
    ): void {
        ActivityLogger::log('shop.'.$action, $subject, $data, $description, $actor);
    }
}
