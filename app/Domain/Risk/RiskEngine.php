<?php

declare(strict_types=1);

namespace App\Domain\Risk;

use App\Enums\RiskLevel;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;

/**
 * Per-withdrawal risk scoring feeding the auto-approve vs manual-review gate
 * (TDD §10.3). Thresholds are configurable data, not constants.
 */
class RiskEngine
{
    public function scoreWithdrawal(User $user, Money $amount, string $toAddress): RiskAssessment
    {
        $score = 0;
        $reasons = [];

        // 1. Large single amount relative to the auto-approve ceiling.
        $autoLimit = (int) getSetting('withdrawal_auto_approve_limit', config('poisapay.withdrawal_auto_approve_limit', 50000));
        $amountMinor = (int) $amount->toDecimal();
        if ($amountMinor > $autoLimit) {
            $score += (int) getSetting('risk_weight_large_amount', 40);
            $reasons[] = 'amount_above_auto_threshold';
        }

        // 2. Velocity: many withdrawals in the configured window.
        $windowHours = (int) getSetting('risk_velocity_window_hours', 24);
        $recent = Withdrawal::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->count();
        if ($recent >= (int) getSetting('risk_velocity_count', 5)) {
            $score += (int) getSetting('risk_weight_velocity', 25);
            $reasons[] = 'high_velocity';
        }

        // 3. Fresh account.
        $freshHours = (int) getSetting('risk_fresh_account_hours', 24);
        if ($user->created_at && $user->created_at->gt(now()->subHours($freshHours))) {
            $score += (int) getSetting('risk_weight_new_account', 20);
            $reasons[] = 'new_account';
        }

        // 4. First time sending to this address.
        $seenAddress = Withdrawal::where('user_id', $user->id)
            ->where('to_address', $toAddress)
            ->where('status', WithdrawalStatus::Completed->value)
            ->exists();
        if (! $seenAddress) {
            $score += (int) getSetting('risk_weight_new_destination', 10);
            $reasons[] = 'new_destination';
        }

        $score = min($score, 100);

        return new RiskAssessment(
            score: $score,
            level: RiskLevel::fromScore($score, [
                'critical' => (int) getSetting('risk_band_critical', 80),
                'high' => (int) getSetting('risk_band_high', 50),
                'medium' => (int) getSetting('risk_band_medium', 25),
            ]),
            reasons: $reasons,
        );
    }
}
