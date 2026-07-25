@php
    $p = $ctx->product;
    $headline = $props['headline'] ?? $p['name'] ?? '';
    $btn = $props['btn'] ?? 'Buy now';
@endphp
<section id="{{ $node->id }}" class="pp-block relative overflow-hidden">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-72" style="background: radial-gradient(60% 100% at 50% 0%, var(--pp-accent), transparent 70%); opacity: .12"></div>
    <div class="relative mx-auto max-w-3xl px-5 py-14 text-center sm:py-20">
        @if (($props['showRating'] ?? true))
            <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white/70 px-3 py-1 text-[11px] font-medium text-neutral-600 shadow-sm">
                <span class="text-amber-500">★★★★★</span> 4.9 · {{ __('loved by 200+ buyers') }}
            </span>
        @endif
        <h1 class="mx-auto mt-5 max-w-2xl text-3xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl">{{ $headline }}</h1>
        @if (! empty($props['tagline']))
            <p class="mx-auto mt-4 max-w-xl text-lg font-medium" style="color: var(--pp-accent)">{{ $props['tagline'] }}</p>
        @endif
        @if (! empty($props['desc']))
            <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-neutral-600">{{ $props['desc'] }}</p>
        @endif

        <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
            @include('builder.blocks._buy', ['label' => $btn, 'class' => 'w-full px-8 py-4 text-sm font-semibold text-white shadow-lg shadow-black/5 transition hover:-translate-y-0.5 hover:opacity-95 sm:w-auto'])
            @if (! empty($p['price']))
                <span class="text-xl font-bold">
                    {{ $p['price'] }}
                    @if (! empty($p['comparePrice']))
                        <span class="ms-1 text-base font-medium text-neutral-400 line-through">{{ $p['comparePrice'] }}</span>
                    @endif
                </span>
            @endif
        </div>

        <div class="mt-7 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-neutral-500">
            <span class="inline-flex items-center gap-1.5"><x-heroicon-o-bolt class="h-4 w-4" style="color: var(--pp-accent)" /> {{ __('Instant access') }}</span>
            <span class="inline-flex items-center gap-1.5"><x-heroicon-o-lock-closed class="h-4 w-4" style="color: var(--pp-accent)" /> {{ __('Secure PoisaPay checkout') }}</span>
            <span class="inline-flex items-center gap-1.5"><x-heroicon-o-shield-check class="h-4 w-4" style="color: var(--pp-accent)" /> {{ __('14-day money-back') }}</span>
        </div>
    </div>
</section>
