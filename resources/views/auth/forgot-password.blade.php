<x-layouts.guest :title="__('Forgot password')">
    <h2 class="text-2xl font-bold tracking-tight text-neutral-900">{{ __('Forgot your password?') }}</h2>
    <p class="mt-1.5 text-sm text-neutral-500">{{ __("Enter your email and we'll send you a link to reset it.") }}</p>

    @if (session('status'))
        <div class="mt-8">
            <x-ui.alert type="success" :title="__('Check your inbox')">{{ session('status') }}</x-ui.alert>
            <p class="mt-4 text-sm text-neutral-500">
                {{ __("Didn't get it? Check your spam folder, or") }}
                <a href="{{ route('password.request') }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('try again') }}</a>.
            </p>
        </div>
    @else
        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
            @csrf
            <x-ui.input :label="__('Email address')" type="email" name="email" :value="old('email')" icon="envelope"
                        :placeholder="__('you@example.com')" autocomplete="email" autofocus :error="$errors->first('email')" />

            <x-ui.button type="submit" class="w-full">{{ __('Send reset link') }}</x-ui.button>
        </form>
    @endif

    <p class="mt-6 text-center text-sm text-neutral-500">
        {{ __('Remembered it?') }}
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Back to sign in') }}</a>
    </p>
</x-layouts.guest>
