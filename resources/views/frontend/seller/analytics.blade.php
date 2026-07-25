<x-layouts.app :title="__('Analytics')">
    <div class="mt-6 space-y-5">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Analytics') }}</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ __('How your sales pages and funnels perform.') }}</p>
        </div>

        {{-- KPIs --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($kpis as [$label, $value, $icon, $tone, $delta])
                <div class="pp-card p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ $label }}</p>
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-neutral-100 text-neutral-500"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" /></span>
                    </div>
                    <p class="tabular mt-2 text-xl font-bold tracking-tight {{ $tone }}">{{ $value }}</p>
                    @if ($delta)<p class="mt-0.5 text-[11px] font-medium text-emerald-600">{{ $delta }} {{ __('vs prev') }}</p>@else<p class="mt-0.5 text-[11px] text-neutral-400">{{ __('Last 30 days') }}</p>@endif
                </div>
            @endforeach
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            {{-- Conversion funnel --}}
            <x-ui.card>
                <p class="mb-4 text-sm font-semibold text-neutral-900">{{ __('Conversion funnel') }}</p>
                <div class="space-y-3">
                    @foreach ($funnel as [$label, $count, $pct])
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="font-medium text-neutral-700">{{ $label }}</span>
                                <span class="tabular text-neutral-500">{{ number_format($count) }} · {{ $pct }}%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-full rounded-full bg-brand-500" style="width: {{ max($pct, 2) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            {{-- Traffic sources --}}
            <x-ui.card>
                <p class="mb-4 text-sm font-semibold text-neutral-900">{{ __('Traffic sources') }}</p>
                <div class="space-y-3">
                    @forelse ($sources as [$label, $pct])
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="font-medium text-neutral-700">{{ $label }}</span>
                                <span class="tabular text-neutral-500">{{ $pct }}%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-full rounded-full bg-neutral-800" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs text-neutral-400">{{ __('No traffic yet — share your sales page to start collecting data.') }}</p>
                    @endforelse
                </div>
            </x-ui.card>
        </div>

        {{-- Pixel/tracking note --}}
        <x-ui.card>
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-o-signal class="h-5 w-5" /></span>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-neutral-900">{{ __('Tracking & pixels') }}</p>
                    <p class="text-xs text-neutral-500">{{ __('Meta Pixel, TikTok, Google Analytics and GTM per sales page — coming soon.') }}</p>
                </div>
                <x-ui.badge color="gray" dot>{{ __('Soon') }}</x-ui.badge>
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>
