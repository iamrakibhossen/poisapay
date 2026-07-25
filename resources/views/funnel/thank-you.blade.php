<x-layouts.sales :title="__('Thank you!')">
    <div class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center px-4 py-16 text-center">
        <span class="grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-emerald-600">
            <x-heroicon-o-check-circle class="h-9 w-9" />
        </span>
        <h1 class="mt-6 text-3xl font-bold tracking-tight text-neutral-900">{{ __('Payment successful') }}</h1>
        <p class="mt-2 text-neutral-600">{{ __('Thank you for your purchase of') }} <span class="font-semibold text-neutral-900">{{ $product['name'] }}</span>.</p>

        {{-- Delivery card --}}
        <div class="mt-8 w-full rounded-2xl border border-neutral-200 bg-white p-5 text-left shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Your download') }}</p>
            <div class="mt-3 flex items-center gap-3 rounded-xl border border-neutral-200 p-3">
                <span class="grid h-11 w-11 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-o-cube class="h-5 w-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-neutral-900">{{ $product['name'] }}</p>
                    <p class="text-xs text-neutral-500">launchkit-v1.0.zip · 24 MB</p>
                </div>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4" /> {{ __('Download') }}
                </button>
            </div>
            <p class="mt-3 text-xs text-neutral-500">{{ __('A copy has also been emailed to you. Access it anytime from your purchases.') }}</p>
        </div>

        {{-- Upsell teaser (funnel next step) --}}
        <div class="mt-4 w-full rounded-2xl border-2 border-dashed border-brand-200 bg-brand-50/40 p-5 text-left">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ __('One-time offer') }}</p>
            <p class="mt-1 text-sm font-semibold text-neutral-900">{{ __('Add the Extended Team License — 40% off') }}</p>
            <p class="mt-0.5 text-xs text-neutral-500">{{ __('Use LaunchKit on unlimited client projects.') }}</p>
            <div class="mt-3 flex gap-2">
                <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Yes, add for $59') }}</button>
                <button class="rounded-lg px-4 py-2 text-sm font-medium text-neutral-500 hover:text-neutral-700">{{ __('No thanks') }}</button>
            </div>
        </div>

        <a href="{{ route('purchases') }}" class="mt-8 text-sm font-medium text-brand-600 hover:text-brand-700">{{ __('Go to my purchases') }} →</a>
    </div>
</x-layouts.sales>
