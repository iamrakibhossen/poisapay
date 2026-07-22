<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Platform activity/audit trail (DollarHub ActivityLogger pattern). Never throws
 * — logging failures are swallowed so a business operation is never blocked by
 * an audit write. Auto-resolves the actor from the admin or web guard.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?string $description = null,
        ?Model $actor = null,
    ): ?AuditLog {
        try {
            $actor ??= Auth::guard('admin')->user() ?? Auth::guard('web')->user();
            $actorType = match (true) {
                $actor instanceof Admin => 'operator',
                $actor instanceof User => 'user',
                default => 'system',
            };

            return AuditLog::create([
                'user_id' => $actor instanceof User ? $actor->getKey() : null,
                'actor_type' => $actorType,
                'actor_id' => $actor?->getKey(),
                'actor_name' => $actor?->name ?? 'System',
                'action' => $action,
                'description' => $description,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'changes' => $properties ?: null,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
