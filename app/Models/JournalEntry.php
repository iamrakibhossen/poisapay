<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'type', 'status', 'idempotency_key', 'reverses_entry_id', 'memo', 'metadata', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EntryStatus::class,
            'metadata' => 'array',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LedgerLine::class, 'entry_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reverses_entry_id');
    }
}
