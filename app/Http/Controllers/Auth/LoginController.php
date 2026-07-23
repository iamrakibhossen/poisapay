<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\DeviceService;
use App\Domain\Auth\TwoFactorService;
use App\Domain\Security\SuspiciousLoginDetector;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Consumer sign-in (traditional controller + Blade, not Livewire). Two-step when
 * 2FA is enabled: step 1 validates credentials and stashes the pending user id +
 * remember choice in the session; step 2 verifies the TOTP code. The password is
 * never carried between steps.
 */
class LoginController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login', [
            'needsTwoFactor' => (bool) $request->session()->get('login_2fa_pending'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Step 2 — TOTP for a session that already passed credentials.
        if ($pendingId = $request->session()->get('login_2fa_pending')) {
            $request->validate(['twoFactorCode' => 'required|string']);
            $user = User::find($pendingId);

            if (! $user || ! app(TwoFactorService::class)->verify($user, (string) $request->input('twoFactorCode'))) {
                throw ValidationException::withMessages(['twoFactorCode' => 'Invalid authentication code.']);
            }

            $request->session()->forget('login_2fa_pending');

            return $this->complete($request, $user, (bool) $request->session()->pull('login_remember', false));
        }

        // Step 1 — credentials.
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $key = 'login:'.md5($request->input('email').$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Auth::getProvider()->validateCredentials($user, ['password' => $request->input('password')])) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('login_2fa_pending', $user->id);
            $request->session()->put('login_remember', $request->boolean('remember'));

            return redirect()->route('login');
        }

        return $this->complete($request, $user, $request->boolean('remember'));
    }

    private function complete(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);
        // Suspicious-login detection MUST run before the device is recorded, so a
        // new device is judged against the devices that existed prior to this login.
        app(SuspiciousLoginDetector::class)->inspect($user, $request);
        app(DeviceService::class)->record($user, $request);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
