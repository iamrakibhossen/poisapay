<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KycStatus;
use App\Enums\KycTier;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'requested_tier', 'status', 'document_type', 'document_number',
        'full_name', 'date_of_birth', 'country', 'address', 'document_paths',
        'liveness_passed', 'reviewed_by', 'reviewed_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_tier' => KycTier::class,
            'status' => KycStatus::class,
            'date_of_birth' => 'date',
            'document_paths' => 'array',
            'liveness_passed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /**
     * Stored path for a document slot (front|back|selfie). New submissions are
     * keyed; legacy submissions were a positional list [front, (back), selfie].
     */
    public function documentPath(string $slot): ?string
    {
        $paths = $this->document_paths ?? [];

        if (array_key_exists($slot, $paths)) {
            return $paths[$slot];
        }

        // Legacy positional fallback.
        $indexed = array_values($paths);
        $count = count($indexed);

        return match ($slot) {
            'front' => $indexed[0] ?? null,
            'selfie' => $count > 1 ? ($indexed[$count - 1] ?? null) : null,
            'back' => $count === 3 ? ($indexed[1] ?? null) : null,
            default => null,
        };
    }
}
