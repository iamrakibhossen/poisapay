<x-layouts.app :title="'Verify your email'">
    <div class="mx-auto max-w-lg">
        <x-ui.card title="{{ __('app.verify_email') }}" subtitle="One more step before you can use PoisaPay.">
            <div class="space-y-5">
                <x-ui.alert type="info" title="{{ __('app.verify_email') }}">
                    We sent a verification link to
                    <span class="font-medium">{{ auth()->user()->email }}</span>.
                    Click the link in that email to activate your account.
                </x-ui.alert>

                <p class="text-sm text-neutral-600">
                    Didn't get the email? Check your spam folder, or request a fresh link below.
                </p>

                <div class="flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <x-ui.button type="submit" icon="envelope">Resend verification email</x-ui.button>
                    </form>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle">
                            {{ __('app.logout') }}
                        </x-ui.button>
                    </form>
                </div>
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>
