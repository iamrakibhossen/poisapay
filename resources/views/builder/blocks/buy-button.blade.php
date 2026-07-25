@php
    $align = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$props['align'] ?? 'center'] ?? 'text-center';
    $p = $ctx->product;
    $label = $props['label'] ?? 'Buy now';
    if (($props['showPrice'] ?? true) && ! empty($p['price'])) {
        $label .= ' · '.$p['price'];
    }
@endphp
<div id="{{ $node->id }}" class="px-5 py-4 {{ $align }}">
    @include('builder.blocks._buy', ['label' => $label, 'class' => 'inline-block px-8 py-4 text-sm font-semibold text-white shadow-lg shadow-black/5 transition hover:-translate-y-0.5 hover:opacity-95'])
</div>
