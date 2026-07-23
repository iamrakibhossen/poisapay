<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\P2p\MerchantStatsService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P2P reputation & trading stats for a user (completion rate, avg release/pay
 * time, volume, online/vacation). Maintained by {@see MerchantStatsService}.
 */
class P2pMerchantProfile extends Model
{
    use HasUuids;

    protected $table = 'p2p_merchant_profiles';

    protected $fillable = [
        'user_id', 'is_online', 'vacation_mode', 'level', 'badges', 'trade_count',
        'completed_count', 'completion_rate_bps', 'avg_release_seconds', 'avg_pay_seconds',
        'total_volume', 'rating',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'vacation_mode' => 'boolean',
            'badges' => 'array',
            'rating' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completionRatePercent(): float
    {
        return round($this->completion_rate_bps / 100, 2);
    }
}
