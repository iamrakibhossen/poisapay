<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An operator-only internal note on a P2P dispute (never shown to the parties).
 *
 * @property string $id
 * @property string $dispute_id
 * @property string $admin_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read P2pDispute $dispute
 * @property-read Admin $admin
 */
class P2pDisputeNote extends Model
{
    use HasUuids;

    protected $table = 'p2p_dispute_notes';

    protected $fillable = ['dispute_id', 'admin_id', 'body'];

    /** @return BelongsTo<P2pDispute, $this> */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(P2pDispute::class, 'dispute_id');
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
