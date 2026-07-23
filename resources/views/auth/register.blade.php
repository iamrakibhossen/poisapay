<x-layouts.guest :title="__('Create account')">
    <h2 class="text-2xl font-bold tracking-tight text-neutral-900">{{ __('Create your account') }}</h2>
    <p class="mt-1.5 text-sm text-neutral-500">{{ __('Start holding and sending crypto in minutes.') }}</p>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
        @csrf
        <x-ui.input :label="__('Full name')" name="name" :value="old('name')" icon="user" :placeholder="__('Rahim Uddin')"
            autocomplete="name" autofocus :error="$errors->first('name')" />
        <x-ui.input :label="__('Email address')" type="email" name="email" :value="old('email')" icon="envelope" :placeholder="__('you@example.com')"
            autocomplete="email" :error="$errors->first('email')" />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input :label="__('Password')" type="password" name="password" icon="lock-closed" :placeholder="__('••••••••')"
                autocomplete="new-password" :error="$errors->first('password')"
                :hint="$errors->has('password') ? null : __('At least 8 characters')" />
            <x-ui.input :label="__('Confirm password')" type="password" name="password_confirmation" icon="lock-closed" :placeholder="__('••••••••')"
                autocomplete="new-password" />
        </div>

        <label class="flex items-start gap-2">
            <input type="checkbox" name="terms" value="1" @checked(old('terms')) class="mt-0.5 rounded border-gray-300 text-brand-500 focus:ring-brand-400" />
            <span class="text-sm text-neutral-600">{{ __('I agree to the') }} <span class="font-medium text-neutral-800">{{ __('Terms of Service') }}</span> {{ __('and') }} <span class="font-medium text-neutral-800">{{ __('Privacy Policy') }}</span>{{ __(', and understand accounts are KYC-gated.') }}</span>
        </label>
        @error('terms')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

        <x-ui.button type="submit" class="w-full">{{ __('Create account') }}</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Sign in') }}</a>
    </p>
</x-layouts.guest>
