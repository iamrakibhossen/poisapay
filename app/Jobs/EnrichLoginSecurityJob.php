<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Notification\NotificationService;
use App\Domain\Security\Contracts\GeoLocator;
use App\Domain\Security\Contracts\IpReputationProvider;
use App\Models\LoginHistory;
use App\Models\SecurityEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Off-request enrichment of a sign-in (Wave 4): consults the IP reputation and
 * geolocation adapters, records country-risk / impossible-travel / flagged-IP
 * signals, updates the login's risk score, and alerts the user when the combined
 * signal is high. Provider-independent — the stubs make no external calls, a real
 * vendor plugs in via config/providers.php with no change here.
 */
class EnrichLoginSecurityJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $loginHistoryId,
        public string $ip,
        public bool $establishedAccount,
    ) {}

    public function handle(
        IpReputationProvider $ipProvider,
        GeoLocator $geo,
        NotificationService $notify,
    ): void {
        $history = LoginHistory::with('user')->find($this->loginHistoryId);
        if (! $history) {
            return;
        }

        $user = $history->user;
        $risk = (int) $history->risk_score;
        $reasons = [];

        // Geolocation (country-risk + impossible-travel).
        if (feature('security_geo_risk', (bool) config('poisapay.security.flags.geo_risk', true))) {
            $loc = $geo->locate($this->ip);
            if ($loc->isKnown()) {
                $history->country = $loc->country;
                $history->city = $loc->city;

                if (in_array($loc->country, (array) config('poisapay.security.high_risk_countries', []), true)) {
                    $risk += 40;
                    $reasons[] = 'high_risk_country';
                    $this->event($user->id, 'new_location', 'critical', 40, ['country' => $loc->country, 'high_risk' => true]);
                }

                $prior = LoginHistory::where('user_id', $user->id)
                    ->where('id', '!=', $history->id)
                    ->whereNotNull('country')
                    ->latest('created_at')->first();

                if ($prior && $prior->country !== $loc->country) {
                    $risk += 25;
                    $reasons[] = 'new_location';
                    $this->event($user->id, 'new_location', 'warning', 25, ['from' => $prior->country, 'to' => $loc->country]);
                }
            }
        }

        // IP reputation (proxy / Tor / abuse).
        if (feature('security_ip_reputation', (bool) config('poisapay.security.flags.ip_reputation', true))) {
            $rep = $ipProvider->check($this->ip);
            $threshold = (int) config('providers.ip_reputation.risk_threshold', 70);
            if ($rep->isRisky($threshold)) {
                $risk += 40;
                $reasons[] = 'ip_flagged';
                $this->event($user->id, 'ip_flagged', 'critical', $rep->riskScore, [
                    'score' => $rep->riskScore, 'proxy' => $rep->isProxy, 'tor' => $rep->isTor,
                ]);
            }
        }

        $risk = min($risk, 100);
        $history->risk_score = $risk;
        $history->save();

        // Alert the user on a genuinely suspicious sign-in (or a new device on an
        // established account). Security-category notifications ignore opt-outs.
        if ($risk >= 40 || ($history->new_device && $this->establishedAccount)) {
            $notify->send($user, 'security.suspicious_login', [
                'title' => 'New sign-in to your account',
                'body' => 'We detected a sign-in from '.($history->city ? $history->city.', ' : '').($history->country ?? 'an unrecognised location')
                    .'. If this was you, no action is needed. If not, change your password and review your devices immediately.',
            ], category: 'security');
        }
    }

    /** @param  array<string, mixed>  $metadata */
    private function event(string $userId, string $type, string $severity, int $risk, array $metadata): void
    {
        SecurityEvent::create([
            'user_id' => $userId,
            'type' => $type,
            'severity' => $severity,
            'ip_address' => $this->ip,
            'risk_score' => $risk,
            'metadata' => $metadata,
        ]);
    }
}
