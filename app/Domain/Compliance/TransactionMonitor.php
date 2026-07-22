<?php

declare(strict_types=1);

namespace App\Domain\Compliance;

use App\Domain\Risk\RiskAssessment;
use App\Enums\RiskLevel;
use App\Enums\ScreeningStatus;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Model;

/**
 * AML transaction monitoring (TDD §10.2). Runs sanctions screening and turns the
 * risk assessment into durable alerts/cases at the moment a money movement is
 * requested. Returns true when the movement must be held for manual review
 * (a sanctions hit or a critical risk score) so the caller can gate it.
 */
class TransactionMonitor
{
    public function __construct(
        private readonly ScreeningService $screening,
        private readonly RaiseAlertAction $raise,
    ) {}

    public function inspectWithdrawal(Withdrawal $withdrawal, RiskAssessment $assessment): bool
    {
        $user = $withdrawal->user;
        $forceReview = false;

        // 1. Sanctions / PEP screening.
        $result = $this->screening->screen($user, 'withdrawal', $withdrawal->id);
        if ($result->result === ScreeningStatus::Hit) {
            $this->raise->execute(
                user: $user,
                type: 'sanctions_hit',
                severity: RiskLevel::Critical,
                score: $result->score,
                reasons: ['sanctions_screen_hit'],
                context: 'withdrawal',
                subject: $withdrawal,
            );
            $forceReview = true;
        } elseif ($result->result === ScreeningStatus::Review) {
            $this->raise->execute(
                user: $user,
                type: 'sanctions_review',
                severity: RiskLevel::High,
                score: $result->score,
                reasons: ['sanctions_screen_review'],
                context: 'withdrawal',
                subject: $withdrawal,
            );
        }

        // 2. Behavioural risk (velocity, amount, new account/destination).
        if ($assessment->requiresManualReview()) {
            $this->raise->execute(
                user: $user,
                type: $this->classify($assessment),
                severity: $assessment->level,
                score: $assessment->score,
                reasons: $assessment->reasons,
                context: 'withdrawal',
                subject: $withdrawal,
            );
            if ($assessment->level === RiskLevel::Critical) {
                $forceReview = true;
            }
        }

        return $forceReview;
    }

    /** Generic entry point for any monitored subject (transfers, deposits, …). */
    public function flag(User $user, string $type, RiskLevel $severity, int $score, array $reasons, string $context, ?Model $subject = null): void
    {
        $this->raise->execute($user, $type, $severity, $score, $reasons, $context, $subject);
    }

    /** Pick the dominant alert type from the assessment reasons. */
    private function classify(RiskAssessment $assessment): string
    {
        return match (true) {
            in_array('amount_above_auto_threshold', $assessment->reasons, true) => 'large_amount',
            in_array('high_velocity', $assessment->reasons, true) => 'velocity',
            in_array('new_account', $assessment->reasons, true) => 'new_account',
            default => 'risk_review',
        };
    }
}
