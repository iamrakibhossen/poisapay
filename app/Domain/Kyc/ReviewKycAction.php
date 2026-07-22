<?php

declare(strict_types=1);

namespace App\Domain\Kyc;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Notification\NotificationService;
use App\Domain\Rewards\ProcessReferralQualificationAction;
use App\Enums\KycStatus;
use App\Events\KycApproved;
use App\Events\KycRejected;
use App\Models\KycProfile;
use App\Models\Referral;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Operator review of a submitted KYC profile (TDD §10.1). Approval upgrades the
 * owning user to the requested tier and — because KYC completion is a qualifying
 * action (TDD §F5) — triggers referral reward processing. Rejection records the
 * reason and leaves the user's tier untouched.
 */
class ReviewKycAction
{
    public function __construct(
        private readonly ProcessReferralQualificationAction $referralQualification,
        private readonly NotificationService $notifications,
    ) {}

    public function approve(KycProfile $profile, Authenticatable $reviewer): void
    {
        DB::transaction(function () use ($profile, $reviewer): void {
            $profile->update([
                'status' => KycStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $user = $profile->user;
            $user->kyc_tier = $profile->requested_tier;
            $user->kyc_status = KycStatus::Approved;
            $user->save();

            ActivityLogger::log('kyc.approved', $profile, ['tier' => $profile->requested_tier->value]);

            KycApproved::dispatch($user->id, $profile->requested_tier->value);

            // KYC completion is a qualifying action for referral rewards (§F5).
            if ($user->referred_by !== null) {
                $referral = Referral::where('referee_id', $user->id)->first();
                if ($referral) {
                    $this->referralQualification->execute($referral);
                }
            }

            $this->notifications->send($user, 'kyc.approved', [
                'tier' => $profile->requested_tier->label(),
            ], url: route('dashboard'));
        });
    }

    public function reject(KycProfile $profile, Authenticatable $reviewer, string $reason): void
    {
        DB::transaction(function () use ($profile, $reviewer, $reason): void {
            $profile->update([
                'status' => KycStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $user = $profile->user;
            $user->kyc_status = KycStatus::Rejected;
            $user->save();

            ActivityLogger::log('kyc.rejected', $profile, ['reason' => $reason]);

            KycRejected::dispatch($user->id, $reason);

            $this->notifications->send($user, 'kyc.rejected', [
                'reason' => $reason,
            ], url: route('kyc'));
        });
    }
}
