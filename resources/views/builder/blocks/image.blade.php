@php
    $w = ['full' => 'max-w-none', 'wide' => 'max-w-4xl', 'medium' => 'max-w-2xl'][$props['width'] ?? 'wide'] ?? 'max-w-4xl';
    $src = $props['src'] ?? '';
@endphp
<div id="{{ $node->id }}" class="mx-auto {{ $w }} px-5 py-6">
    @if ($src)
        <img src="{{ $src }}" alt="{{ $props['alt'] ?? '' }}" loading="lazy" class="w-full rounded-2xl object-cover shadow-sm ring-1 ring-black/[0.03]">
    @elseif ($ctx->editing)
        <div class="grid aspect-video w-full place-items-center rounded-2xl border-2 border-dashed border-neutral-200 text-neutral-400">
            <span class="inline-flex items-center gap-2 text-sm"><x-heroicon-o-photo class="h-5 w-5" /> {{ __('Add an image') }}</span>
        </div>
    @endif
</div>
