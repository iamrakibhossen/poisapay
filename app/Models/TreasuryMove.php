<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $status broadcast | settled | failed
 * @property string $amount
 * @property string|null $onchain_tx_id
 */
class TreasuryMove extends Model
{
    use HasUuids;

    protected $fillable = [
        'chain_id', 'asset_id', 'direction', 'amount', 'status',
        'nonce_context', 'onchain_tx_id', 'settle_entry_id',
    ];
}
