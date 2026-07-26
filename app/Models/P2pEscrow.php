<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\P2pEscrowStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Escrow custody record for an order — the seller's locked USDT. Links the
 * ledger lock/release journal entries so fund movement is fully auditable.
 * Mirrors card_authorizations (hold_entry_id / settle_entry_id).
 *
 * @property string $id
 * @property string $order_id
 * @property string $user_id
 * @property int $asset_id
 * @property string $amount
 * @property P2pEscrowStatus $status
 * @property string|null $lock_entry_id
 * @property string|null $release_entry_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read P2pOrder $order
 * @property-read User $user
 * @property-read Asset $asset
 * @property-read JournalEntry|null $lockEntry
 * @property-read JournalEntry|null $releaseEntry
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

    /** @return BelongsTo<P2pOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(P2pOrder::class, 'order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function lockEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'lock_entry_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function releaseEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'release_entry_id');
    }

    public function money(): Money
    {
        return Money::ofBase($this->amount, $this->asset->decimals, $this->asset->symbol);
    }
}
