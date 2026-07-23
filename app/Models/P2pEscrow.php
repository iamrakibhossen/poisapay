<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\P2pEscrowStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Escrow custody record for an order — the seller's locked USDT. Links the
 * ledger lock/release journal entries so fund movement is fully auditable.
 * Mirrors card_authorizations (hold_entry_id / settle_entry_id).
 */
class P2pEscrow extends Model
{
    use HasUuids;

    protected $table = 'p2p_escrows';

    protected $fillable = [
        'order_id', 'user_id', 'asset_id', 'amount', 'status',
        'lock_entry_id', 'release_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => P2pEscrowStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(P2pOrder::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function lockEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'lock_entry_id');
    }

    public function releaseEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'release_entry_id');
    }

    public function money(): Money
    {
        return Money::ofBase($this->amount, $this->asset->decimals, $this->asset->symbol);
    }
}
