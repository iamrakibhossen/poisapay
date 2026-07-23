<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SweepStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property SweepStatus $status
 * @property string $amount
 * @property string|null $onchain_tx_id
 */
class Sweep extends Model
{
    use HasUuids;

    protected $fillable = [
        'deposit_address_id', 'asset_id', 'amount', 'gas_cost', 'status',
        'nonce_context', 'settle_entry_id', 'onchain_tx_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SweepStatus::class,
        ];
    }

    public function depositAddress(): BelongsTo
    {
        return $this->belongsTo(DepositAddress::class, 'deposit_address_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function settleEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'settle_entry_id');
    }

    public function onchainTx(): BelongsTo
    {
        return $this->belongsTo(OnchainTx::class, 'onchain_tx_id');
    }
}
