<x-layouts.admin-guest :title="__('Reset operator password')">
    <h2 class="text-lg font-semibold text-neutral-900">{{ __('Forgot your password?') }}</h2>
    <p class="mt-1 text-sm text-neutral-500">{{ __("Enter your operator email and we'll send a reset link.") }}</p>

    @if (session('status'))
        <div class="mt-6">
            <x-ui.alert type="success" :title="__('Check your inbox')">{{ session('status') }}</x-ui.alert>
        </div>
    @else
        <form method="POST" action="{{ route('admin.password.email') }}" class="mt-6 space-y-4">
            @csrf
            <x-ui.input :label="__('Email')" type="email" name="email" :value="old('email')" icon="envelope"
                        :placeholder="__('operator@poisapay.test')" autofocus :error="$errors->first('email')" />
            <x-ui.button type="submit" class="w-full">{{ __('Send reset link') }}</x-ui.button>
        </form>
    @endif

    <p class="mt-6 text-center text-sm text-neutral-500">
        <a href="{{ route('admin.login') }}" class="font-medium text-brand-700 hover:text-brand-800">{{ __('Back to sign in') }}</a>
    </p>
</x-layouts.admin-guest>
