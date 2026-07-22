@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconRight' => null,
    'loading' => false,
    'href' => null,
    'type' => 'button',
])

@php
    // DollarHub button base.
    $base = 'inline-flex items-center justify-center gap-2 border rounded-lg font-normal whitespace-nowrap transition ease-in-out duration-150 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none active:scale-[0.98]';

    // Primary is theme-driven: DollarHub gold (dark ink) in admin, near-black in
    // the minimal-themed frontend — see --btn-primary-* in app.css.
    $variants = [
        'primary'   => 'bg-[var(--btn-primary-bg)] text-[var(--btn-primary-fg)] hover:bg-[var(--btn-primary-bg-hover)] border-transparent focus-visible:ring-[var(--btn-primary-bg)]',
        'secondary' => 'bg-[var(--btn-secondary-bg)] text-[var(--btn-secondary-fg)] hover:bg-[var(--btn-secondary-hover)] border-[var(--btn-secondary-border)] focus-visible:ring-gray-400',
        'ghost'     => 'border-transparent text-gray-600 hover:bg-gray-100 focus-visible:ring-gray-400',
        'dark'      => 'bg-ink-800 text-white hover:bg-ink-900 border-transparent focus-visible:ring-ink-800',
        'danger'    => 'bg-red-600 text-white hover:bg-red-500 active:bg-red-700 border-transparent focus-visible:ring-red-400',
        'success'   => 'bg-green-600 text-white hover:bg-green-700 border-transparent focus-visible:ring-green-400',
    ];

    // Heights are pinned so a button always matches an input/select of the same size.
    $sizes = [
        'sm' => 'min-h-[2.25rem] px-3 py-2 text-xs',
        'md' => 'min-h-[2.625rem] px-4 py-2.5 text-sm',
        'lg' => 'min-h-[3rem] px-5 py-3 text-base',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
    $iconSize = $size === 'lg' ? 'h-5 w-5' : 'h-4 w-4';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-dynamic-component :component="'heroicon-o-'.$icon" class="{{ $iconSize }}" />@endif
        {{ $slot }}
        @if ($iconRight)<x-dynamic-component :component="'heroicon-o-'.$iconRight" class="{{ $iconSize }}" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}
        @if ($loading) wire:loading.attr="disabled" @endif>
        @if ($loading)
            <svg wire:loading class="animate-spin {{ $iconSize }}" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
            </svg>
        @endif
        @if ($icon)<span @if($loading) wire:loading.remove @endif><x-dynamic-component :component="'heroicon-o-'.$icon" class="{{ $iconSize }}" /></span>@endif
        {{ $slot }}
        @if ($iconRight)<x-dynamic-component :component="'heroicon-o-'.$iconRight" class="{{ $iconSize }}" />@endif
    </button>
@endif
