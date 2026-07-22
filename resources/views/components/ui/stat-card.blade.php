@props([
    'label' => '',
    'value' => '',
    'icon' => null,
    'trend' => null,        // e.g. '+12.4%'
    'trendUp' => true,
    'accent' => 'brand',    // brand | emerald | amber | rose
])

@php
    // DollarHub stat card: white, colored icon tile, uppercase label, bold value.
    $accents = [
        'brand'   => 'bg-brand-100 text-amber-600',
        'emerald' => 'bg-emerald-100 text-emerald-500',
        'amber'   => 'bg-amber-100 text-amber-500',
        'rose'    => 'bg-rose-100 text-rose-500',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'pp-card group flex items-center gap-4 p-5']) }}>
    @if ($icon)
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg {{ $accents[$accent] ?? $accents['brand'] }}">
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-6 w-6" />
        </span>
    @endif
    <div class="min-w-0 flex-1">
        <p class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $label }}</p>
        <div class="mt-1 flex items-end gap-2">
            <p class="tabular text-2xl font-bold tracking-tight text-neutral-800">{{ $value }}</p>
            @if ($trend)
                <span class="inline-flex items-center gap-0.5 pb-1 text-xs font-semibold {{ $trendUp ? 'text-emerald-600' : 'text-rose-600' }}">
                    <x-dynamic-component :component="'heroicon-m-arrow-trending-'.($trendUp ? 'up' : 'down')" class="h-4 w-4" />
                    {{ $trend }}
                </span>
            @endif
        </div>
    </div>
</div>
