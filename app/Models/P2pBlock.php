<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A P2P block: `user_id` blocked `blocked_id`. A block in either direction stops
 * an order opening between the two and hides their ads from each other.
 *
 * @property string $id
 * @property string $user_id
 * @property string $blocked_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class P2pBlock extends Model
{
    use HasUuids;

    protected $table = 'p2p_blocks';

    protected $fillable = ['user_id', 'blocked_id'];

    /** Is there a block in either direction between two users? */
    public static function existsBetween(string $a, string $b): bool
    {
        return static::query()
            ->where(fn ($q) => $q->where('user_id', $a)->where('blocked_id', $b))
            ->orWhere(fn ($q) => $q->where('user_id', $b)->where('blocked_id', $a))
            ->exists();
    }
}
