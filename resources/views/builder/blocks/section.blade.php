@php
    $width = ['narrow' => 'max-w-2xl', 'medium' => 'max-w-4xl', 'wide' => 'max-w-6xl', 'full' => 'max-w-none'][$props['width'] ?? 'medium'] ?? 'max-w-4xl';
@endphp
<section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
    <div class="mx-auto {{ $width }} px-5">{!! $children !!}</div>
</section>
