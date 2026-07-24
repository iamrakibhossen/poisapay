<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\RollupAnalyticsJob;
use Illuminate\Database\Eloquent\Model;

/**
 * One materialised daily analytics figure (see the create migration). Written by
 * {@see RollupAnalyticsJob}, read by USD-valued time-series reports.
 */
class AnalyticsDailyMetric extends Model
{
    protected $table = 'analytics_daily_metrics';

    protected $fillable = ['day', 'metric', 'value', 'meta'];

    protected $casts = [
        'day' => 'date',
        'value' => 'decimal:2',
        'meta' => 'array',
    ];
}
