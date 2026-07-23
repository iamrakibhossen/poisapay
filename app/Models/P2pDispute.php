<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\P2p\ResolveDisputeAction;
use App\Enums\P2pDisputeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An operator dispute case on an order. Resolution force-releases (buyer) or
 * force-cancels (seller) the escrow via {@see ResolveDisputeAction}.
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(P2pOrder::class, 'order_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(P2pDisputeEvidence::class, 'dispute_id');
    }
}
