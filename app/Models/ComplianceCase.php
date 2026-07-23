<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property CaseStatus $status
 * @property RiskLevel|null $risk_level
 * @property string|null $reason
 * @property bool $sar_filed
 * @property string|null $sar_reference
 * @property string|null $sar_activity_type
 * @property string|null $sar_amount
 * @property Carbon|null $sar_filed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $closed_at
 */
class ComplianceCase extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'status', 'risk_level', 'reason', 'summary',
        'sar_filed', 'sar_reference', 'sar_activity_type', 'sar_narrative', 'sar_amount', 'sar_filed_at',
        'assigned_to', 'opened_by', 'closed_at', 'resolution',
    ];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'risk_level' => RiskLevel::class,
            'sar_filed' => 'boolean',
            'sar_filed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AmlAlert::class, 'case_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'opened_by');
    }
}
