<x-layouts.guest :title="'Reset password'">
    <h2 class="text-2xl font-bold tracking-tight text-neutral-900">Choose a new password</h2>
    <p class="mt-1.5 text-sm text-neutral-500">Set a new password for your PoisaPay account.</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" />

        <x-ui.input label="Email address" type="email" name="email" :value="old('email', $email)" icon="envelope"
                    placeholder="you@example.com" autocomplete="email" :error="$errors->first('email')" />

        <x-ui.input label="New password" type="password" name="password" icon="lock-closed" placeholder="••••••••"
                    autocomplete="new-password" :error="$errors->first('password')"
                    :hint="$errors->has('password') ? null : 'At least 8 characters'" />

        <x-ui.input label="Confirm password" type="password" name="password_confirmation" icon="lock-closed"
                    placeholder="••••••••" autocomplete="new-password" />

        <x-ui.button type="submit" class="w-full">Reset password</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500">
        <a href="{{ route('login') }}" class="font-medium text-amber-700 hover:text-amber-800">Back to sign in</a>
    </p>
</x-layouts.guest>
