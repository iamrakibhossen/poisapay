<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\TwoFactorService;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Operator sign-in (DollarHub structure — controller + Blade, not Livewire).
 * Two-step when 2FA is enabled: step 1 validates credentials and stashes the
 * pending admin id in the session; step 2 verifies the TOTP code. The password is
 * never carried between steps.
 */
class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login', [
            'needsTwoFactor' => (bool) $request->session()->get('admin_2fa_pending'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        // Step 2 — TOTP verification for a session that already passed credentials.
        if ($pendingId = $request->session()->get('admin_2fa_pending')) {
            $request->validate(['twoFactorCode' => 'required|string']);
            $admin = Admin::find($pendingId);

            if (! $admin || ! app(TwoFactorService::class)->verify($admin, (string) $request->input('twoFactorCode'))) {
                throw ValidationException::withMessages(['twoFactorCode' => 'Invalid authentication code.']);
            }

            $request->session()->forget('admin_2fa_pending');

            return $this->complete($request, $admin);
        }

        // Step 1 — credentials.
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $key = 'admin-login:'.md5($request->input('email').$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $admin = Admin::where('email', $request->input('email'))->first();

        if (! $admin || ! Auth::guard('admin')->getProvider()->validateCredentials($admin, ['password' => $request->input('password')])) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        abort_if(! $admin->is_active, 403, 'This operator account is disabled.');

        RateLimiter::clear($key);

        if ($admin->hasTwoFactorEnabled()) {
            $request->session()->put('admin_2fa_pending', $admin->id);

            return redirect()->route('admin.login');
        }

        return $this->complete($request, $admin);
    }

    private function complete(Request $request, Admin $admin): RedirectResponse
    {
        Auth::guard('admin')->login($admin, true);
        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    /** Sign the operator out of the admin guard. */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->regenerate();

        return redirect()->route('admin.login');
    }

    // ── Password reset (operators; uses the dedicated `admins` broker) ──

    public function forgotForm(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::broker('admins')->sendResetLink(['email' => $request->input('email')]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        // Neutral confirmation regardless of whether the operator exists (no enumeration).
        return back()->with('status', 'If that operator account exists, a password reset link is on its way.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker('admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Admin $admin) use ($request): void {
                $admin->forceFill([
                    'password' => Hash::make($request->input('password')),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($admin));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('admin.login')->with('status', 'Your password has been reset. You can now sign in.');
    }
}
