@props([
    'message' => '',
    'position' => 'top',
    'bgColor' => 'bg-gray-900',
    'textColor' => 'text-gray-100',
    'width' => 'w-40',
])

@php
    $positions = [
        'top' => 'bottom-full left-1/2 -translate-x-1/2 -translate-y-2 origin-bottom',
        'bottom' => 'top-full left-1/2 -translate-x-1/2 translate-y-2 origin-top',
        'left' => 'right-full top-1/2 -translate-y-1/2 -translate-x-2 origin-right',
        'right' => 'left-full top-1/2 -translate-y-1/2 translate-x-2 origin-left',
    ];

    $arrowPositions = [
        'top' => '-bottom-1 left-1/2 -translate-x-1/2',
        'bottom' => '-top-1 left-1/2 -translate-x-1/2',
        'left' => 'right-0 top-1/2 -translate-y-1/2 translate-x-1',
        'right' => 'left-0 top-1/2 -translate-y-1/2 -translate-x-1',
    ];

    $positionClasses = $positions[$position] ?? $positions['top'];
    $arrowPositionClasses = $arrowPositions[$position] ?? $arrowPositions['top'];
@endphp

<div x-data="{ tooltip: false }" class="relative inline-flex">
    <div x-on:mouseover="tooltip = true" x-on:mouseleave="tooltip = false" x-on:focus="tooltip = true"
        x-on:blur="tooltip = false">
        {{ $slot }}
    </div>

    <div x-cloak x-show="tooltip" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-95" class="absolute z-[70] {{ $positionClasses }}">
        <div
            class="{{ $width }} px-3 py-1.5 {{ $textColor }} {{ $bgColor }} rounded-lg shadow-lg text-sm font-normal text-center">
            {{ $message }}
        </div>

        <svg class="absolute {{ $arrowPositionClasses }} {{ $bgColor }}" width="8" height="8"
            style="transform: rotate(45deg);">
            <rect x="0" y="0" width="8" height="8" />
        </svg>
    </div>
</div>
