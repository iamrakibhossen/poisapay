<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A registered device push token (FCM/APNs) for a user (Wave 6).
 *
 * @property string $id
 * @property string $user_id
 * @property string $token
 * @property string $platform
 */
class UserPushToken extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'token', 'platform', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
