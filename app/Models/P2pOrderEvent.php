<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only order timeline entry (state transitions), used for audit and the
 * dispute case history.
 */
class P2pOrderEvent extends Model
{
    use HasUuids;

    protected $table = 'p2p_order_events';

    protected $fillable = [
        'order_id', 'actor_type', 'actor_id', 'from_status', 'to_status', 'note',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(P2pOrder::class, 'order_id');
    }
}
