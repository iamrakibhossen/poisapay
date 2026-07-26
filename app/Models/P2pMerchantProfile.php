<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\P2p\MerchantStatsService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * P2P reputation & trading stats for a user (completion rate, avg release/pay
 * time, volume, online/vacation). Maintained by {@see MerchantStatsService}.
 *
 * @property string $id
 * @property string $user_id
 * @property bool $is_online
 * @property Carbon|null $last_seen_at
 * @property bool $vacation_mode
 * @property int $level
 * @property array<int|string, mixed>|null $badges
 * @property int $trade_count
 * @property int $completed_count
 * @property int $completion_rate_bps
 * @property int|null $avg_release_seconds
 * @property int|null $avg_pay_seconds
 * @property string $total_volume
 * @property string $rating
 * @property int $review_count
 * @property int $positive_count
 * @property Carbon|null $featured_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class P2pMerchantProfile extends Model
{
    use HasUuids;

    protected $table = 'p2p_merchant_profiles';

    protected $fillable = [
        'user_id', 'is_online', 'vacation_mode', 'level', 'badges', 'trade_count',
        'completed_count', 'completion_rate_bps', 'avg_release_seconds', 'avg_pay_seconds',
        'total_volume', 'rating', 'review_count', 'positive_count', 'featured_until', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'vacation_mode' => 'boolean',
            'badges' => 'array',
            'rating' => 'decimal:2',
            'featured_until' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** Currently within a granted featured window. */
    public function isFeatured(): bool
    {
        return $this->featured_until !== null && $this->featured_until->isFuture();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completionRatePercent(): float
    {
        return round($this->completion_rate_bps / 100, 2);
    }

    /** Share of received reviews that were positive (rating ≥ 4). */
    public function positivePercent(): float
    {
        return $this->review_count > 0
            ? round($this->positive_count / $this->review_count * 100, 1)
            : 0.0;
    }

    /** Fast enough to offer Express? New merchants get the benefit of the doubt. */
    public function isExpressEligible(): bool
    {
        $max = (int) getSetting('p2p_express_max_release_seconds', 300);

        return $this->avg_release_seconds === null || $this->avg_release_seconds <= $max;
    }

    /** Eligibility check keyed by user — treats a merchant with no profile as new. */
    public static function expressEligible(string $userId): bool
    {
        $profile = static::query()->where('user_id', $userId)->first();

        return $profile === null || $profile->isExpressEligible();
    }
}
