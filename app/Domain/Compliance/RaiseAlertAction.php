<?php

declare(strict_types=1);

namespace App\Domain\Compliance;

use App\Domain\Audit\ActivityLogger;
use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Enums\RiskLevel;
use App\Models\AmlAlert;
use App\Models\ComplianceCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Raise an AML alert and attach it to the user's open compliance case (opening
 * one if none exists). High/critical alerts escalate the case's risk level and
 * ping the operators. Idempotency is the caller's responsibility via the
 * (type, subject) pairing — we de-dupe an identical open alert for the subject.
 */
class RaiseAlertAction
{
    /**
     * @param  array<int, string>  $reasons
     */
    public function execute(
        User $user,
        string $type,
        RiskLevel $severity,
        int $score,
        array $reasons = [],
        string $context = 'withdrawal',
        ?Model $subject = null,
    ): AmlAlert {
        return DB::transaction(function () use ($user, $type, $severity, $score, $reasons, $context, $subject): AmlAlert {
            // De-dupe: an identical still-open alert for the same subject is a no-op.
            $existing = AmlAlert::where('user_id', $user->id)
                ->where('type', $type)
                ->where('status', AlertStatus::Open->value)
                ->when($subject, fn ($q) => $q
                    ->where('subject_type', $subject::class)
                    ->where('subject_id', $subject->getKey()))
                ->first();
            if ($existing) {
                return $existing;
            }

            $case = $this->ensureOpenCase($user, $type, $severity);

            $alert = AmlAlert::create([
                'user_id' => $user->id,
                'type' => $type,
                'severity' => $severity,
                'context' => $context,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'score' => $score,
                'reasons' => $reasons ?: null,
                'status' => $severity === RiskLevel::Critical ? AlertStatus::Escalated : AlertStatus::Open,
                'case_id' => $case->id,
            ]);

            // Bump the case risk to the highest severity seen.
            if ($severity->rank() > $case->risk_level->rank()) {
                $case->update(['risk_level' => $severity]);
            }

            ActivityLogger::log('compliance.alert.raised', $alert, [
                'type' => $type,
                'severity' => $severity->value,
                'case_id' => $case->id,
            ]);

            notifyAdmins(
                'AML alert: '.str_replace('_', ' ', $type),
                "A {$severity->value} alert was raised for {$user->name}.",
                route('admin.compliance'),
                'compliance',
            );

            return $alert;
        });
    }

    private function ensureOpenCase(User $user, string $reason, RiskLevel $severity): ComplianceCase
    {
        $case = ComplianceCase::where('user_id', $user->id)
            ->whereIn('status', [CaseStatus::Open->value, CaseStatus::Investigating->value])
            ->latest()
            ->first();

        return $case ?? ComplianceCase::create([
            'user_id' => $user->id,
            'status' => CaseStatus::Open,
            'risk_level' => $severity,
            'reason' => $reason,
        ]);
    }
}
