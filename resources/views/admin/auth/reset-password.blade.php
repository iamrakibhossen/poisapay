<x-layouts.admin-guest :title="'Reset operator password'">
    <h2 class="text-lg font-semibold text-neutral-900">Choose a new password</h2>
    <p class="mt-1 text-sm text-neutral-500">Set a new password for your operator account.</p>

    <form method="POST" action="{{ route('admin.password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" />
        <x-ui.input label="Email" type="email" name="email" :value="old('email', $email)" icon="envelope"
                    placeholder="operator@poisapay.test" :error="$errors->first('email')" />
        <x-ui.input label="New password" type="password" name="password" icon="lock-closed" placeholder="••••••••"
                    :error="$errors->first('password')" :hint="$errors->has('password') ? null : 'At least 8 characters'" />
        <x-ui.input label="Confirm password" type="password" name="password_confirmation" icon="lock-closed" placeholder="••••••••" />
        <x-ui.button type="submit" class="w-full">Reset password</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500">
        <a href="{{ route('admin.login') }}" class="font-medium text-brand-700 hover:text-brand-800">Back to sign in</a>
    </p>
</x-layouts.admin-guest>
