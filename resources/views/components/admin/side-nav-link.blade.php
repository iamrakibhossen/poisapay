@props(['icon' => 'heroicon-o-link', 'active' => false, 'href' => ''])

@php
    $classes = $active
        ? 'cursor-pointer bg-gray-200 text-gray-600 font-semibold leading-6 flex items-center px-3 py-2.5 rounded-md transition duration-150 ease-in-out'
        : 'cursor-pointer hover:bg-gray-200 text-gray-500 hover:text-gray-500 font-semibold leading-6 flex items-center px-3 py-2.5 rounded-md transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($href) href="{{ $href }}" @endif>
    <span class="w-8">
        @if ($icon)
            @svg($icon, ['class' => 'w-5 h-5'])
        @endif
    </span>
    <span>{{ $slot }}</span>
</a>
