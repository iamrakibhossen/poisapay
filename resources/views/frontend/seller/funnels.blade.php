<x-layouts.app :title="__('Funnels')">
    @php
        $kindTone = [
            'bump' => ['bg-sky-50 text-sky-600', 'text-sky-700'],
            'upsell' => ['bg-emerald-50 text-emerald-600', 'text-emerald-700'],
        ];
    @endphp
    <div class="mx-auto mt-6 max-w-2xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('shop') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900"><x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Shop') }}</a>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Funnels') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Grow each order with bumps and one-click upsells.') }}</p>
            </div>
            <x-ui.button :href="route('shop.sales-pages')" icon="plus">{{ __('New sales page') }}</x-ui.button>
        </div>

        {{-- Performance snapshot --}}
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ($kpis as [$label, $value, $icon, $tone])
                <div class="pp-card p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ $label }}</p>
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-neutral-100 text-neutral-500"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" /></span>
                    </div>
                    <p class="tabular mt-2 text-xl font-bold tracking-tight {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        @forelse ($funnels as $funnel)
            <x-ui.card>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-neutral-900">{{ $funnel['name'] }}</p>
                        <p class="truncate text-xs text-neutral-500">{{ $funnel['product'] }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a href="{{ $funnel['publicUrl'] }}" target="_blank" rel="noopener" class="rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600" title="{{ __('View page') }}">
                            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                        </a>
                        <x-ui.badge color="success" dot>{{ __('Live') }}</x-ui.badge>
                    </div>
                </div>

                {{-- Visual flow --}}
                <div class="mt-6 space-y-0">
                    {{-- Front product --}}
                    <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-neutral-50/60 p-3.5">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-o-cube class="h-4 w-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('Front product') }}</p>
                            <p class="truncate text-sm font-medium text-neutral-900">{{ $funnel['product'] }}</p>
                            <p class="text-xs text-neutral-400">{{ $funnel['orders'] }} {{ __('sales · 30d') }}</p>
                        </div>
                        <span class="text-sm font-semibold text-neutral-900">{{ $funnel['price'] }}</span>
                    </div>

                    @foreach ($funnel['steps'] as $step)
                        {{-- Connector --}}
                        <div class="ms-[26px] h-5 w-px bg-neutral-200"></div>
                        <div class="flex items-center gap-3 rounded-xl border border-neutral-200 p-3.5">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $kindTone[$step['kind']][0] }}">
                                <x-dynamic-component :component="'heroicon-o-'.$step['icon']" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-semibold uppercase tracking-wide {{ $kindTone[$step['kind']][1] }}">{{ $step['label'] }}</p>
                                <p class="truncate text-sm font-medium text-neutral-900">{{ $step['offer'] }}</p>
                                <p class="text-xs text-neutral-400">{{ $step['where'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-neutral-900">{{ $step['price'] }}</p>
                                @if ($step['rate'] !== null)
                                    <p class="text-[11px] text-neutral-400">{{ $step['rate'] }}% {{ __('take') }}</p>
                                @else
                                    <p class="text-[11px] text-neutral-300">{{ __('no data yet') }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @if ($funnel['steps'] === [])
                        <div class="ms-[26px] h-5 w-px bg-neutral-200"></div>
                        <a href="{{ $funnel['editUrl'] }}" class="flex items-center gap-3 rounded-xl border border-dashed border-neutral-300 p-3.5 transition hover:border-brand-400 hover:bg-brand-50/40">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-500"><x-heroicon-o-plus class="h-4 w-4" /></span>
                            <p class="text-sm font-medium text-neutral-600">{{ __('Add an order bump or upsell to grow this order') }}</p>
                        </a>
                    @endif

                    {{-- Thank you --}}
                    <div class="ms-[26px] h-5 w-px bg-neutral-200"></div>
                    <div class="flex items-center gap-3 rounded-xl border border-dashed border-neutral-300 p-3.5">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-600"><x-heroicon-o-check-circle class="h-4 w-4" /></span>
                        <p class="text-sm font-medium text-neutral-700">{{ __('Thank-you page') }}</p>
                    </div>
                </div>

                {{-- Footer: extra revenue + edit --}}
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-100 pt-4">
                    <p class="text-xs text-neutral-500">
                        <span class="font-semibold text-emerald-600">{{ $funnel['extra'] }}</span> {{ __('extra revenue · 30d') }}
                    </p>
                    <a href="{{ $funnel['editUrl'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-600 transition hover:bg-neutral-50">
                        <x-heroicon-o-pencil-square class="h-3.5 w-3.5" /> {{ __('Edit offers') }}
                    </a>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card>
                <div class="py-8 text-center">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-neutral-100 text-neutral-400"><x-heroicon-o-funnel class="h-6 w-6" /></span>
                    <p class="mt-3 text-sm font-semibold text-neutral-900">
                        {{ $hasPages ? __('No published sales pages yet') : __('No sales pages yet') }}
                    </p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-neutral-500">{{ __('Publish a sales page, then add an order bump and a one-click upsell to lift every order.') }}</p>
                    <div class="mt-4">
                        <x-ui.button :href="route('shop.sales-pages')" icon="plus">{{ __('Go to sales pages') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        @endforelse
    </div>
</x-layouts.app>
