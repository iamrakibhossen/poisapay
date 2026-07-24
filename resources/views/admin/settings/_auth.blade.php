<x-admin.form-layout :title="__('Authentication')" :description="__('Registration and verification requirements.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'auth') }}">
        @csrf
        @method('PUT')

        @php
            $toggles = [
                'allow_registration' => [__('Allow Registration'), __('New users can create an account.'), true],
                'email_verification_required' => [__('Require Email Verification'), __('Users must verify their email before transacting.'), true],
                'phone_verification_required' => [__('Require Phone Verification'), __('Users must verify a phone number.'), false],
                'two_factor_required' => [__('Require Two-Factor Authentication'), __('Force 2FA for all accounts.'), false],
            ];
        @endphp

        @foreach ($toggles as $key => [$label, $hint, $default])
            <x-admin.input.group :id="$key" :label="$label" class="w-full" :hints="$hint">
                <x-admin.input.boolean :name="$key" :value="old($key, getSetting($key, $default))" />
            </x-admin.input.group>
        @endforeach

        <h3 class="pt-2 text-sm font-semibold text-gray-700">{{ __('One-Time Passcode (OTP)') }}</h3>

        <x-admin.input.group id="otp_ttl_seconds" :label="__('OTP Lifetime (seconds)')" required class="w-full">
            <x-admin.input type="number" min="30" max="3600" name="otp_ttl_seconds" :value="old('otp_ttl_seconds', getSetting('otp_ttl_seconds', 300))" required />
        </x-admin.input.group>

        <x-admin.input.group id="otp_length" :label="__('OTP Length (digits)')" required class="w-full">
            <x-admin.input type="number" min="4" max="10" name="otp_length" :value="old('otp_length', getSetting('otp_length', 6))" required />
        </x-admin.input.group>

        <x-admin.input.group id="otp_daily_cap" :label="__('OTP Daily Cap (per identifier)')" required class="w-full">
            <x-admin.input type="number" min="1" max="100" name="otp_daily_cap" :value="old('otp_daily_cap', getSetting('otp_daily_cap', 10))" required />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
