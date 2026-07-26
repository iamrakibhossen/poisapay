@php
    $p = $ctx->product;
    $btn = $props['btn'] ?: trim(('Buy now'.($p['price'] ?? '' ? ' · '.$p['price'] : '')));
@endphp
<section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-16 text-center">
    <div class="mx-auto max-w-xl px-5">
        <h2 data-edit="heading" class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] ?? __('Get it today') }}</h2>
        <div class="mt-7">
            @include('builder.blocks._buy', ['label' => $btn, 'class' => 'inline-block px-9 py-4 text-sm font-semibold text-white shadow-lg shadow-black/5 transition hover:-translate-y-0.5 hover:opacity-95'])
        </div>
        <p class="mt-4 inline-flex items-center gap-1.5 text-[11px] text-neutral-400"><x-heroicon-o-lock-closed class="h-3.5 w-3.5" /> {{ __('Secure checkout with your PoishaPay wallet') }}</p>
    </div>
</section>
