<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only order timeline entry (state transitions), used for audit and the
 * dispute case history.
 *
 * @property string $id
 * @property string $order_id
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read P2pOrder $order
 */
class P2pOrderEvent extends Model
{
    use HasUuids;

    protected $table = 'p2p_order_events';

    protected $fillable = [
        'order_id', 'actor_type', 'actor_id', 'from_status', 'to_status', 'note',
    ];

    /** @return BelongsTo<P2pOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(P2pOrder::class, 'order_id');
    }
}
