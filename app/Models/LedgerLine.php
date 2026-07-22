<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerSide;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerLine extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'entry_id', 'account_id', 'asset_id', 'side', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'side' => LedgerSide::class,
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
