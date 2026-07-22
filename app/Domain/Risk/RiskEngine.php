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
        $autoLimit = (int) config('poisapay.withdrawal_auto_approve_limit', 50000);
        $amountMinor = (int) $amount->toDecimal();
        if ($amountMinor > $autoLimit) {
            $score += 40;
            $reasons[] = 'amount_above_auto_threshold';
        }

        // 2. Velocity: many withdrawals in the last 24h.
        $recent = Withdrawal::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($recent >= 5) {
            $score += 25;
            $reasons[] = 'high_velocity';
        }

        // 3. Fresh account (< 24h old).
        if ($user->created_at && $user->created_at->gt(now()->subDay())) {
            $score += 20;
            $reasons[] = 'new_account';
        }

        // 4. First time sending to this address.
        $seenAddress = Withdrawal::where('user_id', $user->id)
            ->where('to_address', $toAddress)
            ->where('status', WithdrawalStatus::Completed->value)
            ->exists();
        if (! $seenAddress) {
            $score += 10;
            $reasons[] = 'new_destination';
        }

        $score = min($score, 100);

        return new RiskAssessment(
            score: $score,
            level: RiskLevel::fromScore($score),
            reasons: $reasons,
        );
    }
}
