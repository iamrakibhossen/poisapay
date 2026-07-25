<?php

declare(strict_types=1);

namespace App\Shop\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only order lifecycle log entry (created_at only — never updated). */
class OrderEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'sell_order_events';

    protected $fillable = ['order_id', 'type', 'actor_type', 'actor_id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
