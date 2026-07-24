@props([
    'kpi' => [],
    'compare' => true,
])

@php
    $accents = [
        'brand'   => ['tile' => 'bg-brand-100 text-brand-700', 'bar' => 'from-brand-400 to-brand-500', 'hex' => '#f59e0b'],
        'emerald' => ['tile' => 'bg-emerald-100 text-emerald-600', 'bar' => 'from-emerald-400 to-emerald-500', 'hex' => '#10b981'],
        'amber'   => ['tile' => 'bg-amber-100 text-amber-600', 'bar' => 'from-amber-400 to-amber-500', 'hex' => '#f59e0b'],
        'rose'    => ['tile' => 'bg-rose-100 text-rose-600', 'bar' => 'from-rose-400 to-rose-500', 'hex' => '#f43f5e'],
        'sky'     => ['tile' => 'bg-sky-100 text-sky-600', 'bar' => 'from-sky-400 to-sky-500', 'hex' => '#0ea5e9'],
        'violet'  => ['tile' => 'bg-violet-100 text-violet-600', 'bar' => 'from-violet-400 to-violet-500', 'hex' => '#8b5cf6'],
    ];
    $a = $accents[$kpi['accent'] ?? 'brand'] ?? $accents['brand'];

    $showTrend = $compare && ! empty($kpi['trend']);
    $good = $kpi['trendGood'] ?? true;
    $up = $kpi['trendUp'] ?? true;

    $spark = $kpi['spark'] ?? [];
    $hasSpark = is_array($spark) && count(array_filter($spark, fn ($v) => (float) $v !== 0.0)) > 0;
@endphp

<div class="pp-card group relative flex flex-col gap-3 overflow-hidden p-5 transition duration-200 hover:-translate-y-0.5 hover:shadow-[var(--shadow-pop)]">
    {{-- Accent bar --}}
    <span class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r {{ $a['bar'] }} opacity-70"></span>

    <div class="flex items-center justify-between">
        <p class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $kpi['label'] }}</p>
        @if (! empty($kpi['icon']))
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg {{ $a['tile'] }} transition group-hover:scale-105">
                <x-dynamic-component :component="'heroicon-o-'.$kpi['icon']" class="h-4 w-4" />
            </span>
        @endif
    </div>

    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
        <p class="font-display tabular text-2xl text-neutral-900">{{ $kpi['value'] }}</p>
        @if ($showTrend)
            <span @class([
                'inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[11px] font-semibold',
                'bg-emerald-50 text-emerald-700' => $good,
                'bg-rose-50 text-rose-700' => ! $good,
            ])>
                <x-dynamic-component :component="'heroicon-m-arrow-trending-'.($up ? 'up' : 'down')" class="h-3 w-3" />
                {{ $kpi['trend'] }}
            </span>
        @endif
    </div>

    @if (! empty($kpi['hint']))
        <p class="-mt-1 text-[11px] text-neutral-400">{{ $kpi['hint'] }}</p>
    @endif

    @if ($hasSpark)
        <div class="-mx-1 -mb-1 mt-auto h-9"
             x-data="{
                init() {
                    window.ppChart(this.$refs.spark, {
                        type: 'line',
                        data: { labels: @js(array_keys($spark)), datasets: [{
                            data: @js(array_values($spark)),
                            borderColor: '{{ $a['hex'] }}',
                            backgroundColor: '{{ $a['hex'] }}1f',
                            fill: true, tension: 0.4, borderWidth: 1.5, pointRadius: 0,
                        }]},
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false }, tooltip: { enabled: false } },
                            scales: { x: { display: false }, y: { display: false } },
                        }
                    });
                }
             }">
            <canvas x-ref="spark"></canvas>
        </div>
    @endif
</div>
