<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\P2pMessageType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message in an order chat thread. `sender_id` is polymorphic (user or admin,
 * null for system messages), so it is intentionally not a constrained FK.
 *
 * @property string $id
 * @property string $order_id
 * @property string $sender_type
 * @property string|null $sender_id
 * @property P2pMessageType $type
 * @property string|null $body
 * @property string|null $attachment_path
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read P2pOrder $order
 */
class P2pOrderMessage extends Model
{
    use HasUuids;

    protected $table = 'p2p_order_messages';

    protected $fillable = [
        'order_id', 'sender_type', 'sender_id', 'type', 'body', 'attachment_path', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => P2pMessageType::class,
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<P2pOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(P2pOrder::class, 'order_id');
    }
}
