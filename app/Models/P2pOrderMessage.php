<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\P2pMessageType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message in an order chat thread. `sender_id` is polymorphic (user or admin,
 * null for system messages), so it is intentionally not a constrained FK.
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(P2pOrder::class, 'order_id');
    }
}
