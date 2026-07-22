<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Records the devices a user signs in from (§8.2). A device is identified by a
 * fingerprint derived from its user-agent + IP, so repeated logins from the same
 * browser update the existing row rather than piling up duplicates.
 */
class DeviceService
{
    public function record(User $user, Request $request): void
    {
        $fingerprint = self::fingerprint($request);

        UserDevice::updateOrCreate(
            ['user_id' => $user->id, 'fingerprint' => $fingerprint],
            [
                'name' => self::label((string) $request->userAgent()),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => Carbon::now(),
            ],
        );
    }

    /** Stable per-browser fingerprint used as the device key. */
    public static function fingerprint(Request $request): string
    {
        return hash('sha256', $request->userAgent().'|'.$request->ip());
    }

    /** Build a friendly "Browser on OS" label from a raw user-agent string. */
    public static function label(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg') => 'Edge',
            str_contains($userAgent, 'OPR') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') || str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        return $browser.' on '.$os;
    }
}
