<x-layouts.admin-guest :title="'Reset operator password'">
    <h2 class="text-lg font-semibold text-neutral-900">Forgot your password?</h2>
    <p class="mt-1 text-sm text-neutral-500">Enter your operator email and we'll send a reset link.</p>

    @if (session('status'))
        <div class="mt-6">
            <x-ui.alert type="success" title="Check your inbox">{{ session('status') }}</x-ui.alert>
        </div>
    @else
        <form method="POST" action="{{ route('admin.password.email') }}" class="mt-6 space-y-4">
            @csrf
            <x-ui.input label="Email" type="email" name="email" :value="old('email')" icon="envelope"
                        placeholder="operator@poisapay.test" autofocus :error="$errors->first('email')" />
            <x-ui.button type="submit" class="w-full">Send reset link</x-ui.button>
        </form>
    @endif

    <p class="mt-6 text-center text-sm text-neutral-500">
        <a href="{{ route('admin.login') }}" class="font-medium text-brand-700 hover:text-brand-800">Back to sign in</a>
    </p>
</x-layouts.admin-guest>
