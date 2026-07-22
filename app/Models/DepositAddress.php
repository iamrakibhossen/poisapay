<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepositAddress extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'chain_id', 'xpub_id', 'derivation_index', 'address', 'is_watched',
    ];

    protected function casts(): array
    {
        return [
            'derivation_index' => 'integer',
            'is_watched' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class);
    }

    public function xpub(): BelongsTo
    {
        return $this->belongsTo(CustodyXpub::class, 'xpub_id');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function sweeps(): HasMany
    {
        return $this->hasMany(Sweep::class);
    }
}
