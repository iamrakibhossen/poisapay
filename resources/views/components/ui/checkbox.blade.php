@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'error' => null,
    'value' => null,
    'align' => 'start', // start (multi-line labels) | center (single-line labels)
])

@php
    $isCenter = $align === 'center';
    $rowAlign = $isCenter ? 'items-center' : 'items-start';
    // Top nudge keeps the box on the first text line; not needed when centered.
    $boxMargin = $isCenter ? '' : 'mt-0.5';
@endphp

{{-- DollarHub checkbox: gold-accented box with an aligned label + optional hint. --}}
<div {{ $attributes->only('class') }}>
    <label class="flex cursor-pointer {{ $rowAlign }} gap-2.5 {{ $attributes->get('disabled') !== false && $attributes->has('disabled') ? 'cursor-not-allowed opacity-60' : '' }}">
        <input
            type="checkbox"
            @if ($name) id="{{ $name }}" name="{{ $name }}" @endif
            @if (! is_null($value)) value="{{ $value }}" @endif
            {{ $attributes->except(['class', 'align'])->merge(['class' => $boxMargin.' h-4 w-4 shrink-0 rounded border-gray-300 text-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-60']) }}
        />
        @if ($label || $hint)
            <span class="min-w-0">
                @if ($label)<span class="text-sm font-medium text-gray-700">{{ $label }}</span>@endif
                @if ($hint)<span class="mt-0.5 block text-xs text-gray-500">{{ $hint }}</span>@endif
            </span>
        @endif
        {{ $slot }}
    </label>
    @if ($error)
        <p class="mt-1.5 text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
