<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Events\OtpRequested;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * One-time passcode issuance and verification (§8.2). Codes are hashed at rest,
 * rate limited per identifier+purpose per day, single-use, and short-lived.
 * Delivery is delegated to a listener via the OtpRequested event — this service
 * never sends mail or SMS itself.
 */
class OtpService
{
    private const MAX_ATTEMPTS = 5;

    /**
     * Issue an OTP for the given identifier, enforcing the daily cap.
     *
     * @param  User|string  $identifier  A user (resolves to its email) or a raw identifier.
     */
    public function request(User|string $identifier, string $channel, string $purpose): OtpCode
    {
        $user = $identifier instanceof User ? $identifier : null;
        $target = $identifier instanceof User ? $identifier->email : $identifier;

        $cap = (int) config('poisapay.otp.daily_cap');
        $issuedToday = OtpCode::where('identifier', $target)
            ->where('purpose', $purpose)
            ->where('created_at', '>=', Carbon::now()->startOfDay())
            ->count();

        if ($issuedToday >= $cap) {
            throw ValidationException::withMessages([
                'identifier' => 'Too many OTP requests. Please try again later.',
            ]);
        }

        $code = $this->generateCode();

        $otp = OtpCode::create([
            'user_id' => $user?->id,
            'identifier' => $target,
            'channel' => $channel,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => Carbon::now()->addSeconds((int) config('poisapay.otp.ttl_seconds')),
        ]);

        OtpRequested::dispatch($target, $channel, $code, $purpose);

        return $otp;
    }

    /** Verify a submitted code against the latest live OTP for identifier+purpose. */
    public function verify(string $identifier, string $purpose, string $code): bool
    {
        $otp = OtpCode::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (! $otp) {
            return false;
        }

        $otp->increment('attempts');

        if ($otp->attempts > self::MAX_ATTEMPTS) {
            $otp->forceFill(['consumed_at' => Carbon::now()])->save();

            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }

        $otp->forceFill(['consumed_at' => Carbon::now()])->save();

        return true;
    }

    private function generateCode(): string
    {
        $length = (int) config('poisapay.otp.length');
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
