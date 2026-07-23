<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Security\AuditChain;
use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\SecurityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin security monitoring dashboard (Wave 4): recent security signals, login
 * anomalies, per-module feature flags, IP denylist management, and audit-chain
 * verification. Controller + Blade (DollarHub structure).
 */
class SecurityController extends Controller
{
    /** The security modules that can be toggled live. */
    private const FLAGS = [
        'withdrawal_whitelist' => 'Withdrawal address whitelist',
        'address_cooldown' => 'New-address cooldown',
        'suspicious_login' => 'Suspicious login detection',
        'ip_reputation' => 'IP reputation checks',
        'geo_risk' => 'Geo-location risk',
        'velocity_limits' => 'Withdrawal velocity limits',
        'audit_hash_chain' => 'Audit log hash chaining',
    ];

    public function index(Request $request): View
    {
        $this->guard();

        $type = (string) $request->query('type', 'all');

        $events = SecurityEvent::query()
            ->with('user')
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $flags = [];
        foreach (self::FLAGS as $key => $label) {
            $flags[$key] = [
                'label' => $label,
                'enabled' => feature("security_{$key}", (bool) config("poisapay.security.flags.{$key}", true)),
            ];
        }

        return view('admin.security', [
            'events' => $events,
            'type' => $type,
            'flags' => $flags,
            'ipDenylist' => (array) getSetting('security_ip_denylist', []),
            'stats' => [
                'critical_24h' => SecurityEvent::where('severity', 'critical')->where('created_at', '>=', now()->subDay())->count(),
                'new_devices_24h' => SecurityEvent::where('type', 'new_device')->where('created_at', '>=', now()->subDay())->count(),
                'logins_24h' => LoginHistory::where('created_at', '>=', now()->subDay())->count(),
                'chain' => session('chain_status'),
            ],
        ]);
    }

    public function toggleFlag(Request $request): RedirectResponse
    {
        $this->guard();
        $key = (string) $request->input('flag');
        abort_unless(array_key_exists($key, self::FLAGS), 404);

        $new = ! feature("security_{$key}", (bool) config("poisapay.security.flags.{$key}", true));
        updateSetting("security_{$key}", $new, 'security');
        ActivityLogger::log('security.flag.toggled', null, ['flag' => $key, 'enabled' => $new]);

        return back()->with('status', self::FLAGS[$key].' '.($new ? 'enabled' : 'disabled').'.');
    }

    public function saveIpDenylist(Request $request): RedirectResponse
    {
        $this->guard();
        $list = collect(preg_split('/[\s,]+/', (string) $request->input('ips', '')))
            ->filter()->unique()->values()->all();
        updateSetting('security_ip_denylist', $list, 'security');
        ActivityLogger::log('security.ip_denylist.updated', null, ['count' => count($list)]);

        return back()->with('status', 'IP denylist updated ('.count($list).' entries).');
    }

    public function verifyChain(): RedirectResponse
    {
        $this->guard();
        $result = AuditChain::verify();

        return back()->with('chain_status', $result)->with(
            'status',
            $result['ok']
                ? "Audit chain verified — {$result['count']} entries intact."
                : "Audit chain BROKEN at sequence {$result['brokenAt']}.",
        );
    }

    private function guard(): void
    {
        abort_unless(
            auth('admin')->user()?->can('view-compliance') || auth('admin')->user()?->hasRole('super-admin'),
            403,
        );
    }
}
