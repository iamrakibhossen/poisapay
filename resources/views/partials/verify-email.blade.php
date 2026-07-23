@if (auth()->check()
    && auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail
    && ! auth()->user()->hasVerifiedEmail()
    && feature('email_verification_required'))
    <div x-data="{ show: true }" x-show="show" x-cloak
         class="mb-6 flex flex-wrap items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-amber-100 text-amber-700">
            <x-heroicon-o-envelope class="h-4 w-4" />
        </span>
        <p class="min-w-0 flex-1 text-sm text-amber-800">
            <span class="font-medium">{{ __('Please verify your email') }}.</span>
            {{ __('Please verify') }} <span class="font-medium">{{ auth()->user()->email }}</span> {{ __('to unlock all features.') }}
        </p>
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit"
                    class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-semibold text-ink-900 hover:bg-amber-600">
                {{ __('Resend email') }}
            </button>
        </form>
        <button type="button" @click="show = false"
                class="rounded-lg p-1.5 text-amber-500 hover:bg-amber-100" aria-label="{{ __('Dismiss') }}">
            <x-heroicon-o-x-mark class="h-4 w-4" />
        </button>
    </div>
@endif
