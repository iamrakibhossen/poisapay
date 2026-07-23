<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One-stop feature-flag console (Wave 6). Surfaces every togglable flag in a single
 * place instead of scattering them across settings sections. Reads/writes the same
 * settings engine the `feature()` helper uses, so toggles take effect immediately.
 */
class FeatureFlagController extends Controller
{
    /**
     * Curated flags grouped for the UI, with each flag's default when unset.
     * (P2P flags are intentionally excluded — owned by the P2P module.)
     */
    private const GROUPS = [
        'Modules' => [
            'deposit_enabled' => true, 'withdrawal_enabled' => true, 'transfer_enabled' => true,
            'exchange_enabled' => true, 'cards_enabled' => true,
            'merchant_enabled' => true, 'rewards_enabled' => true, 'referral_enabled' => true,
        ],
        'Security' => [
            'security_withdrawal_whitelist' => false, 'security_address_cooldown' => true,
            'security_suspicious_login' => true, 'security_ip_reputation' => true,
            'security_geo_risk' => true, 'security_velocity_limits' => true,
            'security_audit_hash_chain' => true, 'security_travel_rule' => false,
        ],
        'Auth' => [
            'email_verification_required' => false, 'phone_verification_required' => false,
        ],
    ];

    public function index(): View
    {
        $this->guard();

        $groups = [];
        foreach (self::GROUPS as $group => $flags) {
            foreach ($flags as $key => $default) {
                $groups[$group][$key] = feature($key, $default);
            }
        }

        return view('admin.feature-flags', ['groups' => $groups]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $this->guard();
        $key = (string) $request->input('flag');

        $known = array_merge(...array_values(self::GROUPS));
        abort_unless(array_key_exists($key, $known), 404);

        $new = ! feature($key, $known[$key]);
        updateSetting($key, $new, 'feature-flag');
        ActivityLogger::log('feature-flag.toggled', null, ['flag' => $key, 'enabled' => $new]);

        return back()->with('status', "{$key} ".($new ? 'enabled' : 'disabled').'.');
    }

    private function guard(): void
    {
        abort_unless(
            auth('admin')->user()?->can('manage-feature-flags') || auth('admin')->user()?->hasRole('super-admin'),
            403,
        );
    }
}
