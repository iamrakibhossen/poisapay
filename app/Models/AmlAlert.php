<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertStatus;
use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string $type
 * @property RiskLevel $severity
 * @property AlertStatus $status
 * @property string|null $context
 * @property int $score
 * @property string|null $case_id
 * @property Carbon|null $created_at
 */
class AmlAlert extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'type', 'severity', 'context', 'subject_type', 'subject_id',
        'score', 'reasons', 'status', 'case_id', 'resolved_by', 'resolved_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'severity' => RiskLevel::class,
            'status' => AlertStatus::class,
            'reasons' => 'array',
            'score' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ComplianceCase::class, 'case_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by');
    }
}
