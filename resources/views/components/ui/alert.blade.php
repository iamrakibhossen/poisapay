@props([
    'type' => 'info',   // info|success|warning|danger
    'title' => null,
    'dismissible' => false,
])

@php
    $map = [
        'info'    => ['wrap' => 'bg-sky-50 border-sky-200 text-sky-800', 'icon' => 'information-circle', 'ic' => 'text-sky-500'],
        'success' => ['wrap' => 'bg-emerald-50 border-emerald-200 text-emerald-800', 'icon' => 'check-circle', 'ic' => 'text-emerald-500'],
        'warning' => ['wrap' => 'bg-amber-50 border-amber-200 text-amber-800', 'icon' => 'exclamation-triangle', 'ic' => 'text-amber-500'],
        'danger'  => ['wrap' => 'bg-rose-50 border-rose-200 text-rose-800', 'icon' => 'x-circle', 'ic' => 'text-rose-500'],
    ];
    $c = $map[$type] ?? $map['info'];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border p-4 text-sm '.$c['wrap']]) }}
    role="alert" @if ($dismissible) x-data="{ show: true }" x-show="show" x-transition x-cloak @endif>
    <x-dynamic-component :component="'heroicon-o-'.$c['icon']" class="mt-0.5 h-5 w-5 shrink-0 {{ $c['ic'] }}" />
    <div class="min-w-0 flex-1">
        @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
        <div class="{{ $title ? 'mt-0.5' : '' }} opacity-90">{{ $slot }}</div>
    </div>
    @if ($dismissible)
        <button type="button" x-on:click="show = false" class="-m-1 shrink-0 rounded-lg p-1 opacity-60 transition hover:opacity-100" aria-label="{{ __('Dismiss') }}">
            <x-heroicon-o-x-mark class="h-4 w-4" />
        </button>
    @endif
</div>
