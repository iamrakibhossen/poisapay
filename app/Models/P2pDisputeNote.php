<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An operator-only internal note on a P2P dispute (never shown to the parties). */
class P2pDisputeNote extends Model
{
    use HasUuids;

    protected $table = 'p2p_dispute_notes';

    protected $fillable = ['dispute_id', 'admin_id', 'body'];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(P2pDispute::class, 'dispute_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
