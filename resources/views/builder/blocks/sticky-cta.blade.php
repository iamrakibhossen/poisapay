@php $p = $ctx->product; $btn = $props['btn'] ?: __('Buy now'); $editing = $ctx->editing; @endphp
<div id="{{ $node->id }}" class="pp-block {{ $editing ? '' : 'fixed inset-x-0 bottom-0 z-40' }}"
    @unless ($editing) x-data="{ show: false }" x-init="window.addEventListener('scroll', () => show = window.scrollY > 480)" x-show="show" x-transition.opacity x-cloak @endunless>
    <div class="border-t border-neutral-200 bg-white/95 px-4 py-3 shadow-[0_-4px_20px_rgba(0,0,0,.06)] backdrop-blur">
        <div class="mx-auto flex max-w-4xl items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-neutral-900">{{ $props['text'] ?: ($p['name'] ?? '') }}</p>
                @if (! empty($p['price']))<p class="text-xs text-neutral-500">{{ $p['price'] }}</p>@endif
            </div>
            @include('builder.blocks._buy', ['label' => $btn, 'class' => 'shrink-0 px-6 py-2.5 text-sm font-semibold text-white'])
        </div>
    </div>
</div>
