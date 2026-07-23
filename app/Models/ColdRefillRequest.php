<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $status requested | approved | broadcast | settled | cancelled
 * @property string $amount
 * @property string|null $tx_hash
 */
class ColdRefillRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'chain_id', 'asset_id', 'amount', 'status', 'cold_address', 'hot_address',
        'tx_hash', 'approved_by', 'approved_at', 'settle_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }
}
