<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\Withdrawal;

/**
 * Withdrawal velocity limiting (Wave 4, feature-flagged). Caps the number of
 * withdrawals a user can initiate in a rolling 24h window; breaching the cap does
 * not reject the withdrawal but forces it into manual review and records a signal.
 */
class VelocityGuard
{
    public function enabled(): bool
    {
        return feature('security_velocity_limits', (bool) config('poisapay.security.flags.velocity_limits', true));
    }

    public function dailyLimit(): int
    {
        return (int) getSetting('security_daily_withdrawal_count', config('poisapay.security.daily_withdrawal_count', 10));
    }

    /** Returns true when the user has hit/exceeded the rolling-24h withdrawal cap. */
    public function exceededWithdrawalVelocity(User $user): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $recent = Withdrawal::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($recent >= $this->dailyLimit()) {
            SecurityEvent::create([
                'user_id' => $user->id,
                'type' => 'velocity_exceeded',
                'severity' => 'warning',
                'ip_address' => request()->ip(),
                'risk_score' => 60,
                'metadata' => ['count_24h' => $recent, 'limit' => $this->dailyLimit()],
            ]);

            return true;
        }

        return false;
    }
}
