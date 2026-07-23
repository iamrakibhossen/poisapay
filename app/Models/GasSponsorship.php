<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $status pending | funded | ready | failed
 * @property int $attempts
 * @property string $amount_sun
 * @property string|null $tx_hash
 */
class GasSponsorship extends Model
{
    use HasUuids;

    protected $fillable = [
        'chain_id', 'target_address', 'purpose', 'status', 'amount_sun',
        'tx_hash', 'attempts', 'last_error', 'funded_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'funded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Chain, $this> */
    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class);
    }
}
