@php $text = $props['text'] ?? ''; $cta = $props['cta'] ?? ''; $href = $props['href'] ?? '#'; @endphp
<section id="{{ $node->id }}" class="pp-block py-2.5 text-center text-sm text-white" style="background: var(--pp-accent)">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-center gap-x-3 gap-y-1 px-5">
        <span class="font-medium">{{ $text }}</span>
        @if (! empty($cta))
            <a href="{{ $href }}" class="inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-0.5 text-xs font-semibold transition hover:bg-white/30">{{ $cta }} <x-heroicon-o-arrow-right class="h-3 w-3" /></a>
        @endif
    </div>
</section>
