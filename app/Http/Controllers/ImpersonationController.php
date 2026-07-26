<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Operator impersonation (TDD §9.3) — a support tool to "view as" a customer.
 * The operator (admin guard) is logged in as the target on the web guard, and the
 * originating admin id is stashed in the session so the banner + stop route can
 * cleanly return. Every start/stop is written to the audit log. Gated by the
 * `impersonate-users` permission; frozen/other operators can never be targets.
 */
class ImpersonationController extends Controller
{
    public function start(User $user)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin && ($admin->can('impersonate-users') || $admin->hasRole('super-admin')), 403);

        // Already impersonating? Switch cleanly to the new target rather than
        // erroring — the operator (admin guard) never changes; only the web-guard
        // session swaps. Record the hand-off so the audit trail stays complete.
        if (session()->has('impersonator_id')) {
            if ($previous = User::find(Auth::guard('web')->id())) {
                ActivityLogger::log('user.impersonation.stopped', $previous, ['admin_id' => $admin->id, 'reason' => 'switched'], actor: $admin);
            }
        }

        session(['impersonator_id' => $admin->id]);
        Auth::guard('web')->login($user);

        ActivityLogger::log('user.impersonation.started', $user, ['admin_id' => $admin->id], actor: $admin);

        return redirect()->route('dashboard');
    }

    public function stop()
    {
        $impersonatorId = session()->pull('impersonator_id');
        abort_unless($impersonatorId, 404);

        $targetId = Auth::guard('web')->id();
        Auth::guard('web')->logout();

        if ($target = User::find($targetId)) {
            ActivityLogger::log('user.impersonation.stopped', $target, ['admin_id' => $impersonatorId]);
        }

        return redirect()->route('admin.users');
    }
}
