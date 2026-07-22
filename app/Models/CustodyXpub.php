<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustodyXpub extends Model
{
    use HasUuids;

    protected $fillable = [
        'chain_id', 'label', 'xpub', 'derivation_path', 'next_index', 'purpose', 'is_active',
    ];

    protected $hidden = ['xpub'];

    protected function casts(): array
    {
        return [
            'next_index' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class);
    }

    public function depositAddresses(): HasMany
    {
        return $this->hasMany(DepositAddress::class, 'xpub_id');
    }
}
