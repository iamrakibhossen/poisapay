@props([
    'symbol' => '',
    'name' => '',
    'available' => '0',
    'locked' => null,
    'secondary' => null,   // e.g. fiat equivalent
])

<div {{ $attributes->merge(['class' => 'pp-card flex items-center gap-4 p-4 transition hover:border-brand-300']) }}>
    <x-ui.asset-icon :symbol="$symbol" size="lg" />
    <div class="min-w-0 flex-1">
        <div class="flex items-center justify-between gap-2">
            <p class="truncate text-sm font-semibold text-neutral-900">{{ $symbol }}</p>
            <p class="tabular text-sm font-semibold text-neutral-900">{{ $available }}</p>
        </div>
        <div class="mt-0.5 flex items-center justify-between gap-2">
            <p class="truncate text-xs text-neutral-500">{{ $name }}</p>
            @if ($secondary)
                <p class="tabular text-xs text-neutral-500">{{ $secondary }}</p>
            @endif
        </div>
        @if ($locked && $locked !== '0')
            <p class="mt-1 text-[11px] text-amber-600">{{ $locked }} {{ __('locked') }}</p>
        @endif
    </div>
</div>
