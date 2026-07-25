<x-layouts.sales :title="__('Thank you!')">
    <div class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center px-4 py-16 text-center">
        <span class="grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-emerald-600">
            <x-heroicon-o-check-circle class="h-9 w-9" />
        </span>
        <h1 class="mt-6 text-3xl font-bold tracking-tight text-neutral-900">{{ __('Payment successful') }}</h1>
        <p class="mt-2 text-neutral-600">{{ __('Thank you for your purchase of') }} <span class="font-semibold text-neutral-900">{{ $product->name }}</span>.</p>

        @if (session('success'))
            <div class="mt-6 w-full rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
        @endif
        @error('upsell')<p class="mt-4 text-sm text-rose-600">{{ $message }}</p>@enderror

        {{-- 1-click upsell — one tap, no re-entry of payment --}}
        @if (! empty($upsell))
            <div class="mt-8 w-full overflow-hidden rounded-2xl border-2 border-brand-300 bg-gradient-to-br from-white to-brand-50/60 text-left shadow-sm">
                <div class="border-b border-brand-100 bg-brand-50/60 px-5 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-brand-700">{{ __('Wait — one-time offer') }}</div>
                <div class="p-5">
                    <p class="text-base font-bold text-neutral-900">{{ $upsell['headline'] }}</p>
                    @if ($upsell['description'])<p class="mt-1 text-sm text-neutral-600">{{ $upsell['description'] }}</p>@endif
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-2xl font-extrabold text-neutral-900">{{ $upsell['price'] }}</span>
                        @if ($upsell['compare'])<span class="text-sm text-neutral-400 line-through">{{ $upsell['compare'] }}</span>@endif
                    </div>
                    <form method="POST" action="{{ route('funnel.upsell', ['slug' => $slug]) }}" x-data="{ loading: false }" x-on:submit="loading = true" class="mt-4">
                        @csrf
                        <input type="hidden" name="order_id" value="{{ $order->id }}" />
                        <button type="submit" x-bind:disabled="loading"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                            <span x-show="! loading" class="flex items-center gap-2"><x-heroicon-o-bolt class="h-4 w-4" /> {{ __('Yes, add it for') }} {{ $upsell['price'] }} — {{ __('one click') }}</span>
                            <span x-show="loading" x-cloak>{{ __('Adding…') }}</span>
                        </button>
                    </form>
                    <p class="mt-2 text-center text-[11px] text-neutral-400">{{ __('Charged to your PoisaPay wallet · no card re-entry') }}</p>
                </div>
            </div>
        @endif

        {{-- Order card --}}
        <div class="mt-8 w-full rounded-2xl border border-neutral-200 bg-white p-5 text-left shadow-sm">
            @if ($order)
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Order') }}</p>
                    <span class="font-mono text-xs text-neutral-500">{{ $order->number }}</span>
                </div>
            @endif
            <div class="mt-3 flex items-center gap-3 rounded-xl border border-neutral-200 p-3">
                <span class="grid h-11 w-11 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-o-cube class="h-5 w-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-neutral-900">{{ $product->name }}</p>
                    <p class="text-xs text-neutral-500">{{ __('Sold by') }} {{ $seller->displayName() }}</p>
                </div>
                @if ($total)
                    <span class="tabular text-sm font-semibold text-neutral-900">{{ $total }}</span>
                @endif
            </div>
            <p class="mt-3 text-xs text-neutral-500">{{ __('Your purchase — including any downloads or license keys — is available anytime from your purchases.') }}</p>
        </div>

        <a href="{{ route('purchases') }}" class="mt-8 inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-700">
            {{ __('Go to my purchases') }} <x-heroicon-o-arrow-right class="h-4 w-4" />
        </a>
    </div>
</x-layouts.sales>
