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

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
