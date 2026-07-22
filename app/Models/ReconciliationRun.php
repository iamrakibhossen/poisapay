<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'asset_id', 'onchain_controlled', 'ledger_treasury', 'ledger_liability',
        'drift', 'is_solvent', 'status',
    ];

    protected function casts(): array
    {
        return [
            'is_solvent' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
