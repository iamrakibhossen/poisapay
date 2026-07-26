<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A viewer's saved (favourite) P2P merchant.
 *
 * @property string $id
 * @property string $user_id
 * @property string $merchant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class P2pFavorite extends Model
{
    use HasUuids;

    protected $table = 'p2p_favorites';

    protected $fillable = ['user_id', 'merchant_id'];
}
