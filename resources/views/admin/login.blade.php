<x-layouts.admin-guest :title="'Operator Sign-in'">
    <h2 class="text-lg font-semibold text-neutral-900">{{ $needsTwoFactor ? 'Two-factor check' : 'Sign in' }}</h2>
    <p class="mt-1 text-sm text-neutral-500">{{ $needsTwoFactor ? 'Enter the code from your authenticator app.' : 'Access the operator console.' }}</p>

    <form method="POST" action="{{ route('admin.login.attempt') }}" class="mt-6 space-y-4">
        @csrf
        @if (! $needsTwoFactor)
            <x-ui.input label="Email" type="email" name="email" :value="old('email')" icon="envelope" placeholder="operator@poisapay.test" autofocus :error="$errors->first('email')" />
            <x-ui.input label="Password" type="password" name="password" icon="lock-closed" placeholder="••••••••" :error="$errors->first('password')" />
        @else
            <x-ui.input label="Authentication code" name="twoFactorCode" icon="shield-check" placeholder="123456" inputmode="numeric" autofocus :error="$errors->first('twoFactorCode')" />
        @endif

        <x-ui.button type="submit" class="w-full">{{ $needsTwoFactor ? 'Verify' : 'Sign in' }}</x-ui.button>
    </form>

    @unless ($needsTwoFactor)
        <p class="mt-4 text-center text-sm text-neutral-500">
            <a href="{{ route('admin.password.request') }}" class="font-medium text-brand-700 hover:text-brand-800">Forgot your password?</a>
        </p>
    @endunless

    <div class="mt-6 rounded-xl border border-dashed border-neutral-300 p-3 text-center text-xs text-neutral-500">
        Demo — <span class="font-mono">admin@poisapay.test</span> / <span class="font-mono">password</span>
    </div>
</x-layouts.admin-guest>
