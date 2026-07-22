<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Compliance\RaiseAlertAction;
use App\Domain\Compliance\ScreeningService;
use App\Enums\RiskLevel;
use App\Enums\ScreeningStatus;
use App\Events\KycSubmitted;
use App\Models\KycProfile;

/**
 * Sanctions/PEP screening at onboarding (TDD §10.2). Runs the moment a KYC
 * application lands; a hit or review-band match raises a compliance alert (and
 * opens a case) so an operator adjudicates before the tier is ever approved.
 * Synchronous — screening is deterministic and cheap.
 */
class ScreenOnKycSubmission
{
    public function __construct(
        private readonly ScreeningService $screening,
        private readonly RaiseAlertAction $raise,
    ) {}

    public function handle(KycSubmitted $event): void
    {
        $profile = KycProfile::with('user')->find($event->profileId);
        if (! $profile || ! $profile->user) {
            return;
        }

        $result = $this->screening->screen($profile->user, 'onboarding', $profile->id);

        if ($result->result === ScreeningStatus::Hit) {
            $this->raise->execute(
                user: $profile->user,
                type: 'sanctions_hit',
                severity: RiskLevel::Critical,
                score: $result->score,
                reasons: ['sanctions_screen_hit'],
                context: 'onboarding',
                subject: $profile,
            );
        } elseif ($result->result === ScreeningStatus::Review) {
            $this->raise->execute(
                user: $profile->user,
                type: 'sanctions_review',
                severity: RiskLevel::High,
                score: $result->score,
                reasons: ['sanctions_screen_review'],
                context: 'onboarding',
                subject: $profile,
            );
        }
    }
}
