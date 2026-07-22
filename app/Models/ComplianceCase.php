<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplianceCase extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'status', 'risk_level', 'reason', 'summary',
        'sar_filed', 'sar_reference', 'assigned_to', 'opened_by', 'closed_at', 'resolution',
    ];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'risk_level' => RiskLevel::class,
            'sar_filed' => 'boolean',
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
