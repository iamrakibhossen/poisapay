<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $address
 * @property string $status active | pending | blocked
 * @property Carbon|null $cooldown_until
 * @property Carbon|null $whitelisted_at
 * @property Carbon|null $blocked_at
 */
class AddressBookEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'label', 'chain_id', 'asset_id', 'address', 'is_favorite', 'last_used_at',
        'status', 'cooldown_until', 'whitelisted_at', 'blocked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'last_used_at' => 'datetime',
            'cooldown_until' => 'datetime',
            'whitelisted_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    /** Still inside its post-creation cooldown window. */
    public function inCooldown(): bool
    {
        return $this->cooldown_until !== null && $this->cooldown_until->isFuture();
    }

    /** Approved AND out of cooldown AND not blocked — usable as a withdrawal destination. */
    public function isWhitelisted(): bool
    {
        return $this->status === 'active' && ! $this->inCooldown() && $this->blocked_at === null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
