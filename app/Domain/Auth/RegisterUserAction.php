<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\ReferralStatus;
use App\Events\UserRegistered;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Onboards a new user (§8): creates the account with an unverified KYC tier,
 * mints a unique referral code, and — when a valid referral code is supplied —
 * links the referrer and opens a pending Referral row for later reward payout.
 */
class RegisterUserAction
{
    /**
     * @param  array{name: string, email: string, phone?: string|null, password: string, referral_code?: string|null}  $data
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $referrer = null;
            if (! empty($data['referral_code'])) {
                $referrer = User::where('referral_code', $data['referral_code'])->first();
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'referral_code' => $this->uniqueReferralCode(),
                'referred_by' => $referrer?->id,
                'kyc_tier' => KycTier::Unverified,
                'kyc_status' => KycStatus::None,
                'base_currency' => 'BDT',
            ]);

            if ($referrer) {
                Referral::create([
                    'referrer_id' => $referrer->id,
                    'referee_id' => $user->id,
                    'code' => $data['referral_code'],
                    'status' => ReferralStatus::Pending,
                ]);
            }

            UserRegistered::dispatch($user->id);

            // Send Laravel's email verification when the gate requires it.
            if (feature('email_verification_required', true)) {
                event(new Registered($user));
            }

            return $user;
        });
    }

    /** Generate an 8-char uppercase alphanumeric code not already in use. */
    private function uniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
