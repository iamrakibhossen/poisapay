<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\P2p\ResolveDisputeAction;
use App\Enums\P2pDisputeStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An operator dispute case on an order. Resolution force-releases (buyer) or
 * force-cancels (seller) the escrow via {@see ResolveDisputeAction}.
 *
 * @property string $id
 * @property string $order_id
 * @property string $opened_by
 * @property string $opened_by_role
 * @property string $reason
 * @property string|null $detail
 * @property P2pDisputeStatus $status
 * @property string|null $assigned_admin_id
 * @property string|null $resolution
 * @property string|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read P2pOrder $order
 * @property-read User $opener
 * @property-read Admin|null $assignedAdmin
 * @property-read Collection<int, P2pDisputeEvidence> $evidence
 * @property-read Collection<int, P2pDisputeNote> $notes
 */
class P2pDispute extends Model
{
    use HasUuids;

    protected $table = 'p2p_disputes';

    protected $fillable = [
        'order_id', 'opened_by', 'opened_by_role', 'reason', 'detail', 'status',
        'assigned_admin_id', 'resolution', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => P2pDisputeStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<P2pOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(P2pOrder::class, 'order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<Admin, $this> */
    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    /** @return HasMany<P2pDisputeEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(P2pDisputeEvidence::class, 'dispute_id');
    }

    /** @return HasMany<P2pDisputeNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(P2pDisputeNote::class, 'dispute_id')->latest();
    }
}
