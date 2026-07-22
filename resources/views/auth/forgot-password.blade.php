<x-layouts.guest :title="'Forgot password'">
    <h2 class="text-2xl font-bold tracking-tight text-neutral-900">Forgot your password?</h2>
    <p class="mt-1.5 text-sm text-neutral-500">Enter your email and we'll send you a link to reset it.</p>

    @if (session('status'))
        <div class="mt-8">
            <x-ui.alert type="success" title="Check your inbox">{{ session('status') }}</x-ui.alert>
            <p class="mt-4 text-sm text-neutral-500">
                Didn't get it? Check your spam folder, or
                <a href="{{ route('password.request') }}" class="font-medium text-amber-700 hover:text-amber-800">try again</a>.
            </p>
        </div>
    @else
        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
            @csrf
            <x-ui.input label="Email address" type="email" name="email" :value="old('email')" icon="envelope"
                        placeholder="you@example.com" autocomplete="email" autofocus :error="$errors->first('email')" />

            <x-ui.button type="submit" class="w-full">Send reset link</x-ui.button>
        </form>
    @endif

    <p class="mt-6 text-center text-sm text-neutral-500">
        Remembered it?
        <a href="{{ route('login') }}" class="font-medium text-amber-700 hover:text-amber-800">Back to sign in</a>
    </p>
</x-layouts.guest>
