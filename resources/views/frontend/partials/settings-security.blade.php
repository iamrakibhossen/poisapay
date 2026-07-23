{{-- Settings › Security tab — two-factor enrolment + phone verification.
     Expects (from the settings view scope): $twoFactorEnabled, $twoFactorSetup,
     $phoneVerified, $hasPhone, $profile, $errors. --}}
<x-settings.section :title="__('Two-factor authentication')" :description="__('Add an extra layer of security with an authenticator app.')">
    @if ($twoFactorEnabled)
        <div class="space-y-4">
            <div class="flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 p-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                    <x-heroicon-o-shield-check class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-neutral-900">{{ __('Two-factor authentication is on') }}</p>
                    <p class="text-xs text-neutral-500">{{ __('Your account is protected with an authenticator app.') }}</p>
                </div>
                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Enabled') }}</span>
            </div>
            <form method="POST" action="{{ route('settings.2fa.disable') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary">{{ __('Disable 2FA') }}</x-ui.button>
            </form>
        </div>
    @elseif ($twoFactorSetup)
        <div class="space-y-5">
            <p class="text-sm text-neutral-500">{{ __('Scan this QR code with your authenticator app, then enter the 6-digit code to confirm.') }}</p>
            <div class="flex flex-col gap-5 sm:flex-row">
                <div class="grid place-items-center rounded-xl border border-neutral-200 bg-white p-3">{!! $twoFactorSetup['qr'] !!}</div>
                <div class="flex-1">
                    <p class="pp-label">{{ __('Recovery codes') }}</p>
                    <p class="mb-2 text-xs text-neutral-500">{{ __('Store these safely. Each can be used once if you lose access to your app.') }}</p>
                    <div class="grid grid-cols-2 gap-1.5 rounded-xl bg-neutral-50 p-3 font-mono text-xs">
                        @foreach ($twoFactorSetup['recoveryCodes'] as $code)
                            <span class="text-neutral-700">{{ $code }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('settings.2fa.confirm') }}" class="max-w-xs space-y-4">
                @csrf
                <x-ui.input :label="__('Confirmation code')" name="confirmCode" placeholder="123456" inputmode="numeric" :error="$errors->first('confirmCode')" />
                <x-ui.button type="submit" variant="dark">{{ __('Confirm & enable') }}</x-ui.button>
            </form>
        </div>
    @else
        <div class="space-y-4">
            <div class="flex items-center gap-3 rounded-xl border border-amber-100 bg-amber-50/60 p-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-amber-100 text-amber-600">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-neutral-900">{{ __('Two-factor is off') }}</p>
                    <p class="text-xs text-neutral-500">{{ __('Protect your account with a time-based one-time passcode.') }}</p>
                </div>
                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">{{ __('Off') }}</span>
            </div>
            <form method="POST" action="{{ route('settings.2fa.enable') }}">
                @csrf
                <x-ui.button type="submit" variant="dark" icon="shield-check">{{ __('Enable 2FA') }}</x-ui.button>
            </form>
        </div>
    @endif
</x-settings.section>

<x-settings.section :title="__('Phone verification')" :description="__('Confirm your phone number with a one-time code.')">
    @if ($phoneVerified)
        <div class="flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 p-4">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                <x-heroicon-o-check-circle class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-neutral-900">{{ __('Phone verified') }}</p>
                <p class="truncate text-xs text-neutral-500">{{ $profile['phone'] }}</p>
            </div>
            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Verified') }}</span>
        </div>
    @elseif (! $hasPhone)
        <p class="text-sm text-neutral-500">{{ __('Add a phone number in the') }}
            <a href="{{ route('settings.index', ['tab' => 'profile']) }}" class="font-medium text-neutral-900 underline underline-offset-2">{{ __('Profile') }}</a>
            {{ __('tab first, then verify it here.') }}</p>
    @else
        <div class="max-w-sm space-y-4">
            <p class="text-sm text-neutral-500">{{ __("We'll text a 6-digit code to") }} <span class="font-medium text-neutral-900">{{ $profile['phone'] }}</span>.</p>
            @if (! session('otpSent') && ! $errors->has('phoneOtp'))
                <form method="POST" action="{{ route('settings.phone.otp') }}">
                    @csrf
                    <x-ui.button type="submit" variant="dark">{{ __('Verify phone') }}</x-ui.button>
                </form>
                @error('phone')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            @else
                <form method="POST" action="{{ route('settings.phone.verify') }}" class="space-y-3">
                    @csrf
                    <x-ui.input :label="__('Verification code')" name="phoneOtp" placeholder="123456" inputmode="numeric" :error="$errors->first('phoneOtp')" />
                    <x-ui.button type="submit" variant="dark">{{ __('Confirm code') }}</x-ui.button>
                </form>
                <form method="POST" action="{{ route('settings.phone.otp') }}">
                    @csrf
                    <x-ui.button type="submit" variant="ghost" size="sm">{{ __('Resend') }}</x-ui.button>
                </form>
            @endif
        </div>
    @endif
</x-settings.section>

{{-- Withdrawal address whitelist --}}
<x-settings.section :title="__('Withdrawal addresses')"
    :description="$whitelistEnforced
        ? __('Withdrawals are restricted to whitelisted addresses.')
        : __('Save trusted addresses. New addresses wait :hours h before they can be used.', ['hours' => $cooldownHours])">
    <form method="POST" action="{{ route('security.address.add') }}" class="flex flex-col gap-3 sm:flex-row">
        @csrf
        <input name="label" placeholder="{{ __('Label (optional)') }}" class="pp-input sm:w-40" />
        <input name="address" placeholder="{{ __('Address') }}" required class="pp-input flex-1" />
        <x-ui.button type="submit" variant="dark" icon="plus">{{ __('Add') }}</x-ui.button>
    </form>

    <ul class="mt-4 divide-y divide-neutral-100">
        @forelse ($addresses as $a)
            <li class="flex items-center justify-between gap-3 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-neutral-900">{{ $a->label }}</p>
                    <p class="truncate font-mono text-xs text-neutral-500">{{ $a->address }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    @if ($a->inCooldown())
                        <x-ui.badge color="warning">{{ __('Cooldown') }} · {{ $a->cooldown_until->diffForHumans() }}</x-ui.badge>
                    @elseif ($a->status === 'blocked')
                        <x-ui.badge color="danger">{{ __('Blocked') }}</x-ui.badge>
                    @else
                        <x-ui.badge color="success" dot>{{ __('Whitelisted') }}</x-ui.badge>
                    @endif
                    <form method="POST" action="{{ route('security.address.delete', $a->id) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs font-medium text-neutral-400 hover:text-rose-600">{{ __('Remove') }}</button>
                    </form>
                </div>
            </li>
        @empty
            <li class="py-4 text-sm text-neutral-400">{{ __('No saved addresses yet.') }}</li>
        @endforelse
    </ul>
</x-settings.section>

{{-- Anti-phishing code --}}
<x-settings.section :title="__('Anti-phishing code')" :description="__('A phrase we include in every genuine email so you can spot fakes.')">
    <form method="POST" action="{{ route('security.anti-phishing') }}" class="flex flex-col gap-3 sm:flex-row">
        @csrf @method('PUT')
        <input name="anti_phishing_code" value="{{ $antiPhishing }}" maxlength="32"
            placeholder="{{ __('e.g. blue-otter-42') }}" class="pp-input flex-1" />
        <x-ui.button type="submit" variant="dark" icon="check">{{ __('Save') }}</x-ui.button>
    </form>
</x-settings.section>

{{-- Recent security events --}}
<x-settings.section :title="__('Recent security events')" :description="__('Sign-ins, address changes and other sensitive activity on your account.')">
    <ul class="divide-y divide-neutral-100">
        @forelse ($securityEvents as $e)
            <li class="flex items-center justify-between gap-3 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-neutral-900">{{ ucfirst(str_replace('_', ' ', $e->type)) }}</p>
                    <p class="truncate text-xs text-neutral-500">{{ $e->ip_address }} · {{ $e->created_at->diffForHumans() }}</p>
                </div>
                <x-ui.badge :color="match ($e->severity) { 'critical' => 'danger', 'warning' => 'warning', default => 'gray' }">{{ ucfirst($e->severity) }}</x-ui.badge>
            </li>
        @empty
            <li class="py-4 text-sm text-neutral-400">{{ __('No security events.') }}</li>
        @endforelse
    </ul>
</x-settings.section>
