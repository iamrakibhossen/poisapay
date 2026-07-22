<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardDispute extends Model
{
    use HasUuids;

    protected $fillable = [
        'authorization_id', 'reason', 'status', 'amount', 'entry_id',
    ];

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(CardAuthorization::class, 'authorization_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'entry_id');
    }
}
