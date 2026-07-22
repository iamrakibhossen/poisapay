{{-- Settings › Security tab — two-factor enrolment + phone verification.
     Expects (from the settings view scope): $twoFactorEnabled, $twoFactorSetup,
     $phoneVerified, $hasPhone, $profile, $errors. --}}
<x-settings.section title="Two-factor authentication" description="Add an extra layer of security with an authenticator app.">
    @if ($twoFactorEnabled)
        <div class="space-y-4">
            <div class="flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 p-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                    <x-heroicon-o-shield-check class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-neutral-900">Two-factor authentication is on</p>
                    <p class="text-xs text-neutral-500">Your account is protected with an authenticator app.</p>
                </div>
                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Enabled</span>
            </div>
            <form method="POST" action="{{ route('settings.2fa.disable') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary">Disable 2FA</x-ui.button>
            </form>
        </div>
    @elseif ($twoFactorSetup)
        <div class="space-y-5">
            <p class="text-sm text-neutral-500">Scan this QR code with your authenticator app, then enter the 6-digit code to confirm.</p>
            <div class="flex flex-col gap-5 sm:flex-row">
                <div class="grid place-items-center rounded-xl border border-neutral-200 bg-white p-3">{!! $twoFactorSetup['qr'] !!}</div>
                <div class="flex-1">
                    <p class="pp-label">Recovery codes</p>
                    <p class="mb-2 text-xs text-neutral-500">Store these safely. Each can be used once if you lose access to your app.</p>
                    <div class="grid grid-cols-2 gap-1.5 rounded-xl bg-neutral-50 p-3 font-mono text-xs">
                        @foreach ($twoFactorSetup['recoveryCodes'] as $code)
                            <span class="text-neutral-700">{{ $code }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('settings.2fa.confirm') }}" class="max-w-xs space-y-4">
                @csrf
                <x-ui.input label="Confirmation code" name="confirmCode" placeholder="123456" inputmode="numeric" :error="$errors->first('confirmCode')" />
                <x-ui.button type="submit" variant="dark">Confirm &amp; enable</x-ui.button>
            </form>
        </div>
    @else
        <div class="space-y-4">
            <div class="flex items-center gap-3 rounded-xl border border-amber-100 bg-amber-50/60 p-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-amber-100 text-amber-600">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-neutral-900">Two-factor is off</p>
                    <p class="text-xs text-neutral-500">Protect your account with a time-based one-time passcode.</p>
                </div>
                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Off</span>
            </div>
            <form method="POST" action="{{ route('settings.2fa.enable') }}">
                @csrf
                <x-ui.button type="submit" variant="dark" icon="shield-check">Enable 2FA</x-ui.button>
            </form>
        </div>
    @endif
</x-settings.section>

<x-settings.section title="Phone verification" description="Confirm your phone number with a one-time code.">
    @if ($phoneVerified)
        <div class="flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 p-4">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                <x-heroicon-o-check-circle class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-neutral-900">Phone verified</p>
                <p class="truncate text-xs text-neutral-500">{{ $profile['phone'] }}</p>
            </div>
            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Verified</span>
        </div>
    @elseif (! $hasPhone)
        <p class="text-sm text-neutral-500">Add a phone number in the
            <a href="{{ route('settings', ['tab' => 'profile']) }}" class="font-medium text-neutral-900 underline underline-offset-2">Profile</a>
            tab first, then verify it here.</p>
    @else
        <div class="max-w-sm space-y-4">
            <p class="text-sm text-neutral-500">We'll text a 6-digit code to <span class="font-medium text-neutral-900">{{ $profile['phone'] }}</span>.</p>
            @if (! session('otpSent') && ! $errors->has('phoneOtp'))
                <form method="POST" action="{{ route('settings.phone.otp') }}">
                    @csrf
                    <x-ui.button type="submit" variant="dark">Verify phone</x-ui.button>
                </form>
                @error('phone')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            @else
                <form method="POST" action="{{ route('settings.phone.verify') }}" class="space-y-3">
                    @csrf
                    <x-ui.input label="Verification code" name="phoneOtp" placeholder="123456" inputmode="numeric" :error="$errors->first('phoneOtp')" />
                    <x-ui.button type="submit" variant="dark">Confirm code</x-ui.button>
                </form>
                <form method="POST" action="{{ route('settings.phone.otp') }}">
                    @csrf
                    <x-ui.button type="submit" variant="ghost" size="sm">Resend</x-ui.button>
                </form>
            @endif
        </div>
    @endif
</x-settings.section>
