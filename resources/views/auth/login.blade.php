<x-layouts.guest :title="'Sign in'">
    <h2 class="text-2xl font-bold tracking-tight text-neutral-900">Welcome back</h2>
    <p class="mt-1.5 text-sm text-neutral-500">Sign in to your PoisaPay account.</p>

    @if (session('status'))
        <div class="mt-6">
            <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}" class="mt-8 space-y-5">
        @csrf
        @if (! $needsTwoFactor)
            <x-ui.input label="Email address" type="email" name="email" :value="old('email')" icon="envelope"
                        placeholder="you@example.com" autofocus :error="$errors->first('email')" />

            <div>
                <div class="flex items-center justify-between">
                    <label class="pp-label mb-0">Password</label>
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Forgot password?</a>
                </div>
                <div class="mt-1.5">
                    <x-ui.input type="password" name="password" icon="lock-closed"
                                placeholder="••••••••" :error="$errors->first('password')" />
                </div>
            </div>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-400" />
                <span class="text-sm leading-4 text-neutral-600">Keep me signed in</span>
            </label>
        @else
            <x-ui.alert type="info" title="Two-factor authentication">Enter the 6-digit code from your authenticator app.</x-ui.alert>
            <x-ui.input label="Authentication code" name="twoFactorCode" icon="shield-check"
                        placeholder="123456" inputmode="numeric" autofocus :error="$errors->first('twoFactorCode')" />
        @endif

        <x-ui.button type="submit" class="w-full">{{ $needsTwoFactor ? 'Verify & continue' : 'Sign in' }}</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-neutral-500">
        New to PoisaPay?
        <a href="{{ route('register') }}" class="font-medium text-brand-600 hover:text-brand-700">Create an account</a>
    </p>
</x-layouts.guest>
