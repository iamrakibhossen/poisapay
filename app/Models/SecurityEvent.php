<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A durable security signal (suspicious login, velocity breach, whitelist block).
 *
 * @property string $id
 * @property string|null $user_id
 * @property string $type
 * @property string $severity
 * @property string|null $ip_address
 * @property string|null $country
 * @property string|null $city
 * @property string|null $user_agent
 * @property string|null $fingerprint
 * @property int $risk_score
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $acknowledged_at
 * @property-read User|null $user
 */
class SecurityEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'type', 'severity', 'ip_address', 'country', 'city',
        'user_agent', 'fingerprint', 'risk_score', 'metadata', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'risk_score' => 'integer',
            'acknowledged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
