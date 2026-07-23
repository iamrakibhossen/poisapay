<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Auth\DeviceService;
use App\Domain\Auth\OtpService;
use App\Domain\Auth\TwoFactorService;
use App\Domain\Security\AddressBookService;
use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Account settings — server-rendered. Profile, two-factor enrolment, phone OTP
 * verification, device/session management. {@see index()} renders every section;
 * each mutation POSTs its own route and redirects back with a flash message.
 * Security-sensitive — 2FA secrets and recovery codes are surfaced once during
 * enrolment via {@see enableTwoFactor()} (flashed to the session).
 */
class SettingsController extends Controller
{
    public function index(Request $request, ?string $tab = null): View
    {
        $user = $request->user();
        $tabs = ['profile', 'security', 'password', 'verification', 'devices', 'preferences', 'sessions'];
        $activeTab = in_array($tab, $tabs, true) ? $tab : 'profile';
        $currentFingerprint = DeviceService::fingerprint($request);

        $devices = $user->devices()->latest('last_used_at')->get()->map(fn (UserDevice $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'ip' => $d->ip_address,
            'last' => $d->last_used_at?->diffForHumans(),
            'current' => $d->fingerprint === $currentFingerprint,
        ]);

        $sessions = DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'ip' => $s->ip_address,
                'agent' => $s->user_agent,
                'last' => Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                'current' => $s->id === session()->getId(),
            ]);

        $priorities = $user->spendingPriority()->with('asset')->get()
            ->map(fn ($p) => ['symbol' => $p->asset?->symbol ?? '?', 'name' => $p->asset?->name]);

        $kycStatus = $user->kyc_status;

        // Security-centre data (folded in from the former standalone /security page).
        $addressBook = app(AddressBookService::class);
        $addressBook->promoteMatured($user);

        return view('frontend.settings', [
            'profile' => [
                'name' => (string) $user->name,
                'phone' => (string) $user->phone,
                'baseCurrency' => (string) ($user->base_currency ?: 'BDT'),
                'timezone' => (string) ($user->timezone ?: 'Asia/Dhaka'),
            ],
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
            'phoneVerified' => ! is_null($user->phone_verified_at),
            'hasPhone' => filled($user->phone),
            'priorities' => $priorities,
            'devices' => $devices,
            'sessions' => $sessions,
            'kyc' => ['key' => $kycStatus->value, 'label' => $kycStatus->label(), 'color' => $kycStatus->color()],
            'canApplyKyc' => in_array($kycStatus, [KycStatus::None, KycStatus::Rejected], true),
            'activeTab' => $activeTab,
            // Security centre (now part of the Security + Sessions tabs).
            'addresses' => $user->addressBook()->get(),
            'whitelistEnforced' => $addressBook->whitelistEnforced(),
            'cooldownHours' => $addressBook->cooldownHours(),
            'antiPhishing' => (string) $user->anti_phishing_code,
            'securityEvents' => $user->securityEvents()->limit(20)->get(),
            'loginHistory' => $user->loginHistories()->limit(20)->get(),
        ]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'baseCurrency' => ['required', 'string', 'max:8'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $user->name = $validated['name'];
        $user->phone = ($validated['phone'] ?? '') !== '' ? $validated['phone'] : null;
        $user->base_currency = $validated['baseCurrency'];
        $user->timezone = $validated['timezone'];
        $user->save();

        return redirect()->route('settings.index', ['tab' => 'profile'])->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $request->user()->update(['password' => Hash::make($validated['password'])]);
        ActivityLogger::log('security.password.updated', null, [], actor: $request->user());

        return redirect()->route('settings.index', ['tab' => 'password'])->with('success', 'Password updated.');
    }

    public function enableTwoFactor(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $result = $twoFactor->enable($request->user());

        // Secret QR + recovery codes are shown once, during enrolment — flashed to the session.
        return redirect()->route('settings.index', ['tab' => 'security'])->with('twoFactorSetup', [
            'qr' => $result['qr_svg'],
            'recoveryCodes' => $result['recovery_codes'],
        ]);
    }

    public function confirmTwoFactor(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $code = trim((string) $request->input('confirmCode'));
        if ($code === '') {
            throw ValidationException::withMessages(['confirmCode' => 'Enter the 6-digit code from your app.']);
        }
        if (! $twoFactor->confirm($request->user(), $code)) {
            throw ValidationException::withMessages(['confirmCode' => 'That code is invalid. Please try again.']);
        }

        return redirect()->route('settings.index', ['tab' => 'security'])->with('success', 'Two-factor authentication enabled.');
    }

    public function disableTwoFactor(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $twoFactor->disable($request->user());

        return redirect()->route('settings.index', ['tab' => 'security'])->with('success', 'Two-factor authentication disabled.');
    }

    public function sendPhoneOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $user = $request->user();
        if (blank($user->phone)) {
            throw ValidationException::withMessages(['phone' => 'Add a phone number in your profile first.']);
        }

        $otp->request($user->phone, 'sms', 'verify');

        return redirect()->route('settings.index', ['tab' => 'security'])->with('success', 'Verification code sent.')->with('otpSent', true);
    }

    public function verifyPhone(Request $request, OtpService $otp): RedirectResponse
    {
        $user = $request->user();
        if (blank($user->phone)) {
            throw ValidationException::withMessages(['phone' => 'Add a phone number in your profile first.']);
        }

        $code = trim((string) $request->input('phoneOtp'));
        if ($code === '' || ! $otp->verify($user->phone, 'verify', $code)) {
            throw ValidationException::withMessages(['phoneOtp' => 'That code is invalid or has expired.']);
        }

        $user->phone_verified_at = now();
        $user->save();
        ActivityLogger::log('phone.verified', $user);

        return redirect()->route('settings.index', ['tab' => 'security'])->with('success', 'Phone number verified.');
    }

    public function revokeDevice(Request $request, string $id): RedirectResponse
    {
        $device = $request->user()->devices()->whereKey($id)->first();
        if ($device) {
            $device->delete();
            ActivityLogger::log('device.revoked', $device);
        }

        return redirect()->route('settings.index', ['tab' => 'devices'])->with('success', 'Device revoked.');
    }
}
