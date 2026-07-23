<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Enums\KycTier;
use App\Models\P2pMerchantProfile;
use App\Models\User;
use Brick\Math\BigInteger;

/**
 * Derives a trader's level and badges from their P2P stats. Pure computation;
 * {@see MerchantStatsService} calls recompute() whenever the counters change.
 */
class P2pReputationService
{
    private const LEVEL_LABELS = ['New', 'Bronze', 'Silver', 'Gold', 'Platinum'];

    /** Level 0–4 from completed trade count. */
    public function level(int $completed): int
    {
        return match (true) {
            $completed >= 1000 => 4,
            $completed >= 200 => 3,
            $completed >= 50 => 2,
            $completed >= 10 => 1,
            default => 0,
        };
    }

    public function levelLabel(int $level): string
    {
        return self::LEVEL_LABELS[$level] ?? 'New';
    }

    /**
     * @return array<int, string>
     */
    public function badges(P2pMerchantProfile $profile, ?User $user): array
    {
        $badges = [];

        if ($user && $user->tier() === KycTier::Full) {
            $badges[] = 'verified';
        }
        if ($profile->trade_count >= 20 && $profile->completion_rate_bps >= 9500) {
            $badges[] = 'reliable';
        }
        if ($profile->completed_count >= 10 && $profile->avg_release_seconds !== null && $profile->avg_release_seconds <= 300) {
            $badges[] = 'fast_release';
        }
        if ($profile->completed_count >= 10 && $profile->avg_pay_seconds !== null && $profile->avg_pay_seconds <= 900) {
            $badges[] = 'fast_pay';
        }
        // 100,000 USDT lifetime volume (6dp base units).
        if (BigInteger::of((string) ($profile->total_volume ?: '0'))->isGreaterThanOrEqualTo(BigInteger::of('100000000000'))) {
            $badges[] = 'high_volume';
        }

        return $badges;
    }

    public function badgeLabel(string $key): string
    {
        return match ($key) {
            'verified' => 'Verified',
            'reliable' => 'Reliable',
            'fast_release' => 'Fast release',
            'fast_pay' => 'Fast pay',
            'high_volume' => 'High volume',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    /** Recompute and persist level + badges from the profile's current counters. */
    public function recompute(P2pMerchantProfile $profile): void
    {
        $user = User::find($profile->user_id);

        $profile->update([
            'level' => $this->level($profile->completed_count),
            'badges' => $this->badges($profile, $user),
        ]);
    }
}
