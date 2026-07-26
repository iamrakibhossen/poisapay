{{-- Shared checkout button. References the single hidden #buy form on the page
     (rendered by the sales layout / preview shell). Accent + radius come from the
     document's global design tokens. --}}
@php
    $buyClass = $class ?? 'px-8 py-4 text-sm font-semibold text-white shadow-lg shadow-black/5 transition hover:-translate-y-0.5 hover:opacity-95';
    // Callers on accent/dark backgrounds pass a white-button style; default stays accent.
    $buyStyle = $style ?? 'background: var(--pp-accent); border-radius: var(--pp-btn-radius)';
@endphp
<button type="submit" form="{{ $ctx->buyFormId }}" @disabled($ctx->editing)
    class="{{ $buyClass }}"
    style="{{ $buyStyle }}">
    {{ $label }}
</button>
