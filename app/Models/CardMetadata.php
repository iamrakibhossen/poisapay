<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Generic per-card provider key/value. */
class CardMetadata extends Model
{
    use HasUuids;

    protected $table = 'card_metadata';

    protected $fillable = ['card_id', 'key', 'value'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
