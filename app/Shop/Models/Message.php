<?php

declare(strict_types=1);

namespace App\Shop\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A message in an order's shared conversation. There is no thread table — the
 * order IS the conversation; messages reference it directly and are tagged with
 * the author side (buyer|seller|operator|system).
 */
class Message extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'sell_messages';

    protected $fillable = ['order_id', 'author_type', 'author_id', 'body', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }
}
