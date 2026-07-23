<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Auth\DeviceService;
use App\Jobs\EnrichLoginSecurityJob;
use App\Models\LoginHistory;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;

/**
 * Suspicious-login detection (Wave 4). The fast, synchronous path records the
 * login and flags a new device; the slower external checks (IP reputation, geo,
 * impossible-travel) run in {@see EnrichLoginSecurityJob}. Login history is
 * captured even when detection is disabled, closing the prior audit gap.
 *
 * MUST run BEFORE DeviceService::record() so "new device" is judged against the
 * devices that existed prior to this sign-in.
 */
class SuspiciousLoginDetector
{
    public function enabled(): bool
    {
        return feature('security_suspicious_login', (bool) config('poisapay.security.flags.suspicious_login', true));
    }

    public function inspect(User $user, Request $request): void
    {
        $fingerprint = DeviceService::fingerprint($request);
        $newDevice = ! UserDevice::where('user_id', $user->id)->where('fingerprint', $fingerprint)->exists();
        $establishedAccount = LoginHistory::where('user_id', $user->id)->exists();

        $history = LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'fingerprint' => $fingerprint,
            'new_device' => $newDevice,
            'risk_score' => $newDevice ? 30 : 0,
        ]);

        if (! $this->enabled()) {
            return;
        }

        if ($newDevice && $establishedAccount) {
            SecurityEvent::create([
                'user_id' => $user->id,
                'type' => 'new_device',
                'severity' => 'warning',
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'fingerprint' => $fingerprint,
                'risk_score' => 30,
                'metadata' => ['device' => DeviceService::label((string) $request->userAgent())],
            ]);
        }

        // Slow external checks + alerting run off the request path.
        EnrichLoginSecurityJob::dispatch(
            $history->id,
            (string) $request->ip(),
            $establishedAccount,
        );
    }
}
