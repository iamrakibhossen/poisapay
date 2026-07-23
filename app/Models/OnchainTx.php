<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OnchainTxStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $chain_id
 * @property string $tx_hash
 * @property int $log_index
 * @property string|null $from_address
 * @property string|null $to_address
 * @property int|null $asset_id
 * @property string $amount
 * @property int|null $block_number
 * @property int $confirmations
 * @property OnchainTxStatus $status
 * @property string $direction
 */
class OnchainTx extends Model
{
    use HasUuids;

    protected $table = 'onchain_txs';

    protected $fillable = [
        'chain_id', 'tx_hash', 'log_index', 'from_address', 'to_address',
        'asset_id', 'amount', 'block_number', 'confirmations', 'status', 'direction',
    ];

    protected function casts(): array
    {
        return [
            'log_index' => 'integer',
            'block_number' => 'integer',
            'confirmations' => 'integer',
            'status' => OnchainTxStatus::class,
        ];
    }

    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
