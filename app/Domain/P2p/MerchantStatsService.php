<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Models\P2pMerchantProfile;
use App\Models\P2pOrder;
use Brick\Math\BigInteger;

/**
 * Maintains P2P reputation counters (trade count, completion rate, volume,
 * average pay/release times) on {@see P2pMerchantProfile}. Pure bookkeeping.
 */
class MerchantStatsService
{
    public function __construct(private readonly P2pReputationService $reputation) {}

    public function recordCompletion(P2pOrder $order): void
    {
        $paySeconds = $order->buyer_paid_at && $order->created_at
            ? (int) $order->created_at->diffInSeconds($order->buyer_paid_at)
            : null;
        $releaseSeconds = $order->released_at && $order->buyer_paid_at
            ? (int) $order->buyer_paid_at->diffInSeconds($order->released_at)
            : null;

        $this->bump($order->seller_id, true, (string) $order->crypto_amount, null, $releaseSeconds);
        $this->bump($order->buyer_id, true, '0', $paySeconds, null);
    }

    /** A trade that did not complete (cancel/expire/force-cancel) — counts against completion rate. */
    public function recordFailure(P2pOrder $order): void
    {
        $this->bump($order->seller_id, false, '0', null, null);
    }

    private function bump(string $userId, bool $completed, string $volumeBase, ?int $paySeconds, ?int $releaseSeconds): void
    {
        $profile = P2pMerchantProfile::firstOrCreate(
            ['user_id' => $userId],
            ['trade_count' => 0, 'completed_count' => 0, 'completion_rate_bps' => 0, 'total_volume' => '0'],
        );

        $trades = (int) $profile->trade_count + 1;
        $done = (int) $profile->completed_count + ($completed ? 1 : 0);
        $currentVolume = (string) $profile->total_volume === '' ? '0' : (string) $profile->total_volume;

        $profile->update([
            'trade_count' => $trades,
            'completed_count' => $done,
            'completion_rate_bps' => $trades > 0 ? intdiv($done * 10_000, $trades) : 0,
            'total_volume' => (string) BigInteger::of($currentVolume)->plus($volumeBase),
            'avg_pay_seconds' => $this->rollingAverage($profile->avg_pay_seconds, $done, $paySeconds),
            'avg_release_seconds' => $this->rollingAverage($profile->avg_release_seconds, $done, $releaseSeconds),
        ]);

        // Refresh derived level + badges from the new counters.
        $this->reputation->recompute($profile);
    }

    private function rollingAverage(?int $current, int $count, ?int $sample): ?int
    {
        if ($sample === null) {
            return $current;
        }
        if ($current === null || $count <= 1) {
            return $sample;
        }

        return (int) round((($current * ($count - 1)) + $sample) / $count);
    }
}
