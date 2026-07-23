<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file/note attached to a dispute by either party or an operator.
 */
class P2pDisputeEvidence extends Model
{
    use HasUuids;

    protected $table = 'p2p_dispute_evidence';

    protected $fillable = [
        'dispute_id', 'uploaded_by', 'uploader_role', 'path', 'note',
    ];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(P2pDispute::class, 'dispute_id');
    }
}
