@php
    $w = [
        '1/2' => 'sm:w-1/2', '1/3' => 'sm:w-1/3', '2/3' => 'sm:w-2/3',
        '1/4' => 'sm:w-1/4', 'full' => 'w-full', 'auto' => 'flex-1',
    ][$props['width'] ?? 'auto'] ?? 'flex-1';
@endphp
<div id="{{ $node->id }}" class="{{ $w }} min-w-0">{!! $children !!}</div>
