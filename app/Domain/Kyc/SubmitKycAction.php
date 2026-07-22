<?php

declare(strict_types=1);

namespace App\Domain\Kyc;

use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Events\KycSubmitted;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Submit a KYC application (TDD §10.1): opens a pending KycProfile capturing the
 * requested tier, identity document fields and uploaded document paths, and
 * flips the user into a Pending review state. Fires KycSubmitted for downstream
 * screening / operator queues.
 */
class SubmitKycAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): KycProfile
    {
        return DB::transaction(function () use ($user, $data): KycProfile {
            $requestedTier = $data['requested_tier'] instanceof KycTier
                ? $data['requested_tier']
                : KycTier::from((string) ($data['requested_tier'] ?? KycTier::Basic->value));

            $profile = KycProfile::create([
                'user_id' => $user->id,
                'requested_tier' => $requestedTier,
                'status' => KycStatus::Pending,
                'document_type' => $data['document_type'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'full_name' => $data['full_name'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'country' => $data['country'] ?? null,
                'address' => $data['address'] ?? null,
                'document_paths' => $data['document_paths'] ?? [],
                'liveness_passed' => $data['liveness_passed'] ?? false,
            ]);

            $user->kyc_status = KycStatus::Pending;
            $user->save();

            KycSubmitted::dispatch($profile->id);

            return $profile;
        });
    }
}
