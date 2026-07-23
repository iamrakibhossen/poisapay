<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversionContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversion extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'quote_id', 'context', 'entry_id', 'idempotency_key',
        'status', 'completed_at', 'spread_amount', 'fee_amount', 'gross_amount', 'notional_usd',
    ];

    protected function casts(): array
    {
        return [
            'context' => ConversionContext::class,
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(FxQuote::class, 'quote_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'entry_id');
    }
}
