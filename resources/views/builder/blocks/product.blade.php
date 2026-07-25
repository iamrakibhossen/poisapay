@php $p = $ctx->product; @endphp
<section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-12">
    <div class="mx-auto max-w-md px-5">
        <div class="overflow-hidden rounded-2xl border border-neutral-200 shadow-sm ring-1 ring-black/[0.02]">
            <div class="flex items-center gap-3 border-b border-neutral-100 px-5 py-4">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl text-white" style="background: var(--pp-accent)"><x-heroicon-o-cube class="h-6 w-6" /></span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold">{{ $props['name'] ?: ($p['name'] ?? '') }}</p>
                    <p class="text-[11px] uppercase tracking-wide text-neutral-400">{{ $p['type'] ?? '' }}</p>
                </div>
            </div>
            <div class="px-5 py-4">
                <p class="text-sm text-neutral-600">{{ $props['summary'] ?: ($p['summary'] ?? '') }}</p>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-2xl font-bold">{{ $p['price'] ?? '' }}</span>
                    @if (! empty($p['comparePrice']))
                        <span class="text-sm text-neutral-400 line-through">{{ $p['comparePrice'] }}</span>
                    @endif
                </div>
                <div class="mt-4">
                    @include('builder.blocks._buy', ['label' => $props['btn'] ?? 'Buy now', 'class' => 'w-full py-3 text-sm font-semibold text-white transition hover:opacity-90'])
                </div>
                @if (! empty($props['note']))
                    <p class="mt-2 text-center text-[11px] text-neutral-400">{{ $props['note'] }}</p>
                @endif
            </div>
        </div>
    </div>
</section>
