<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file/note attached to a dispute by either party or an operator.
 *
 * @property string $id
 * @property string $dispute_id
 * @property string $uploaded_by
 * @property string $uploader_role
 * @property string $path
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read P2pDispute $dispute
 */
class P2pDisputeEvidence extends Model
{
    use HasUuids;

    protected $table = 'p2p_dispute_evidence';

    protected $fillable = [
        'dispute_id', 'uploaded_by', 'uploader_role', 'path', 'note',
    ];

    /** @return BelongsTo<P2pDispute, $this> */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(P2pDispute::class, 'dispute_id');
    }
}
