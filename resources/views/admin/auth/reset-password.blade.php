<x-layouts.admin-guest :title="__('Reset operator password')">
    <h2 class="text-lg font-semibold text-neutral-900">{{ __('Choose a new password') }}</h2>
    <p class="mt-1 text-sm text-neutral-500">{{ __('Set a new password for your operator account.') }}</p>

    <form method="POST" action="{{ route('admin.password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" />
        <x-ui.input :label="__('Email')" type="email" name="email" :value="old('email', $email)" icon="envelope"
                    :placeholder="__('operator@poisapay.test')" :error="$errors->first('email')" />
        <x-ui.input :label="__('New password')" type="password" name="password" icon="lock-closed" :placeholder="__('••••••••')"
                    :error="$errors->first('password')" :hint="$errors->has('password') ? null : __('At least 8 characters')" />
        <x-ui.input :label="__('Confirm password')" type="password" name="password_confirmation" icon="lock-closed" :placeholder="__('••••••••')" />
        <x-ui.button type="submit" class="w-full">{{ __('Reset password') }}</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500">
        <a href="{{ route('admin.login') }}" class="font-medium text-brand-700 hover:text-brand-800">{{ __('Back to sign in') }}</a>
    </p>
</x-layouts.admin-guest>
