<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpcEndpoint extends Model
{
    use HasUuids;

    protected $fillable = [
        'chain_id', 'name', 'url', 'priority', 'weight', 'is_active',
        'status', 'last_block', 'latency_ms', 'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'weight' => 'integer',
            'is_active' => 'boolean',
            'last_block' => 'integer',
            'latency_ms' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'up' => 'success',
            'degraded' => 'warning',
            'down' => 'danger',
            default => 'gray',
        };
    }
}
