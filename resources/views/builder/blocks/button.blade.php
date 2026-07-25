@php
    $align = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$props['align'] ?? 'center'] ?? 'text-center';
    $isBuy = ($props['action'] ?? 'buy') === 'buy';
@endphp
<div id="{{ $node->id }}" class="{{ $align }}">
    @if ($isBuy)
        @include('builder.blocks._buy', ['label' => $props['label'] ?? 'Learn more', 'class' => 'inline-block px-8 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:opacity-95'])
    @else
        <a href="{{ $ctx->editing ? '#' : ($props['href'] ?? '#') }}"
            class="inline-block px-8 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:opacity-95"
            style="background: var(--pp-accent); border-radius: var(--pp-btn-radius)">
            {{ $props['label'] ?? 'Learn more' }}
        </a>
    @endif
</div>
