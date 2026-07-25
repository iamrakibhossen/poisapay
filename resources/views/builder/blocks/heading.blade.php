@php
    $tag = in_array($props['level'] ?? 'h2', ['h1', 'h2', 'h3'], true) ? $props['level'] : 'h2';
    $align = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$props['align'] ?? 'center'] ?? 'text-center';
    $size = ['h1' => 'text-3xl sm:text-5xl font-extrabold', 'h2' => 'text-2xl sm:text-3xl font-bold', 'h3' => 'text-xl font-bold'][$tag];
@endphp
<{{ $tag }} id="{{ $node->id }}" class="{{ $align }} {{ $size }} tracking-tight text-neutral-900">{{ $props['text'] ?? '' }}</{{ $tag }}>
