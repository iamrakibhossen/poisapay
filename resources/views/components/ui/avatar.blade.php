@props(['name' => '', 'size' => 'md', 'src' => null])

@php
    $sizes = ['sm' => 'h-8 w-8 text-xs', 'md' => 'h-10 w-10 text-sm', 'lg' => 'h-12 w-12 text-base'];
    $dim = $sizes[$size] ?? $sizes['md'];
    $initials = collect(explode(' ', trim($name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $hue = crc32($name) % 360;
@endphp

@if ($src)
    <img {{ $attributes->merge(['class' => 'shrink-0 rounded-full object-cover '.$dim]) }}
        src="{{ $src }}" alt="{{ $name }}" />
@else
    <span {{ $attributes->merge(['class' => 'inline-grid shrink-0 place-items-center rounded-full font-semibold text-white '.$dim]) }}
        style="background: hsl({{ $hue }} 60% 45%);">
        {{ $initials ?: '?' }}
    </span>
@endif
