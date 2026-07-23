<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Security\AuditChain;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'actor_type', 'actor_id', 'actor_name', 'action', 'description',
        'subject_type', 'subject_id', 'changes', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'sequence' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Tamper-evident hash chaining (Wave 4). Runs before insert so the row is
        // sealed as it is written. Never blocks the audit write on failure.
        static::creating(function (AuditLog $log): void {
            try {
                AuditChain::assign($log);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
