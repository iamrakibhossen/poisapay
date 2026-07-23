<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single successful sign-in, retained for the user's security activity view.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $ip_address
 * @property string|null $country
 * @property string|null $city
 * @property string|null $user_agent
 * @property string|null $fingerprint
 * @property bool $new_device
 * @property int $risk_score
 * @property Carbon|null $created_at
 * @property-read User $user
 */
class LoginHistory extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'login_histories';

    protected $fillable = [
        'user_id', 'ip_address', 'country', 'city', 'user_agent',
        'fingerprint', 'new_device', 'risk_score',
    ];

    protected function casts(): array
    {
        return [
            'new_device' => 'boolean',
            'risk_score' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
