@php
    $align = ['top' => 'items-start', 'center' => 'items-center', 'bottom' => 'items-end'][$props['align'] ?? 'top'] ?? 'items-start';
@endphp
<div id="{{ $node->id }}" class="flex flex-col gap-6 sm:flex-row {{ $align }}">{!! $children !!}</div>
