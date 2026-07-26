<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** A viewer's saved (favourite) P2P merchant. */
class P2pFavorite extends Model
{
    use HasUuids;

    protected $table = 'p2p_favorites';

    protected $fillable = ['user_id', 'merchant_id'];
}
