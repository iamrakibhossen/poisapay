<x-layouts.app :title="__('Funnels')">
    @php
        $kindTone = [
            'bump' => ['bg-sky-50 text-sky-600', 'text-sky-700'],
            'upsell' => ['bg-emerald-50 text-emerald-600', 'text-emerald-700'],
            'downsell' => ['bg-amber-50 text-amber-600', 'text-amber-700'],
        ];
    @endphp
    <div class="mx-auto mt-6 max-w-2xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Funnels') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Grow each order with bumps and one-click upsells.') }}</p>
            </div>
            <x-ui.button icon="plus">{{ __('New funnel') }}</x-ui.button>
        </div>

        {{-- Funnel card --}}
        <x-ui.card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-neutral-900">{{ $funnel['name'] }}</p>
                    <p class="text-xs text-neutral-500">{{ $funnel['product'] }}</p>
                </div>
                <x-ui.badge color="success" dot>{{ __('Active') }}</x-ui.badge>
            </div>

            {{-- Visual flow --}}
            <div class="mt-6 space-y-0">
                {{-- Front product --}}
                <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-neutral-50/60 p-3.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-o-cube class="h-4 w-4" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('Front product') }}</p>
                        <p class="truncate text-sm font-medium text-neutral-900">{{ $funnel['product'] }}</p>
                    </div>
                    <span class="text-sm font-semibold text-neutral-900">{{ $funnel['price'] }}</span>
                </div>

                @foreach ($funnel['steps'] as $step)
                    {{-- Connector --}}
                    <div class="ms-[26px] h-5 w-px bg-neutral-200"></div>
                    <div class="group flex items-center gap-3 rounded-xl border border-neutral-200 p-3.5 transition hover:border-neutral-300">
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
                            <p class="text-[11px] text-neutral-400">{{ $step['rate'] }}% {{ __('take') }}</p>
                        </div>
                        <button type="button" class="ms-1 rounded-lg p-1.5 text-neutral-300 transition hover:bg-neutral-100 hover:text-neutral-600">
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                        </button>
                    </div>
                @endforeach

                {{-- Thank you --}}
                <div class="ms-[26px] h-5 w-px bg-neutral-200"></div>
                <div class="flex items-center gap-3 rounded-xl border border-dashed border-neutral-300 p-3.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-600"><x-heroicon-o-check-circle class="h-4 w-4" /></span>
                    <p class="text-sm font-medium text-neutral-700">{{ __('Thank-you page') }}</p>
                </div>
            </div>

            {{-- Add step --}}
            <div class="mt-5 flex flex-wrap gap-2 border-t border-neutral-100 pt-4">
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-600 transition hover:bg-neutral-50">
                    <x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add order bump') }}
                </button>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-600 transition hover:bg-neutral-50">
                    <x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add upsell') }}
                </button>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-600 transition hover:bg-neutral-50">
                    <x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add downsell') }}
                </button>
            </div>
        </x-ui.card>

        {{-- Funnel performance --}}
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['Extra revenue · 30d', '$1,284', 'arrow-trending-up', 'text-emerald-600'],
                ['Avg. order value', '$71.40', 'banknotes', 'text-neutral-900'],
                ['Upsell take rate', '22%', 'sparkles', 'text-neutral-900'],
            ] as [$label, $value, $icon, $tone])
                <div class="pp-card p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ $label }}</p>
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-neutral-100 text-neutral-500"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" /></span>
                    </div>
                    <p class="tabular mt-2 text-xl font-bold tracking-tight {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
