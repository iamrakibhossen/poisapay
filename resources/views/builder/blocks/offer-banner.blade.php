@php $btn = $props['btn'] ?: __('Claim offer'); @endphp
<section id="{{ $node->id }}" class="pp-block py-10">
    <div class="mx-auto max-w-4xl px-5">
        <div class="relative overflow-hidden rounded-3xl bg-neutral-900 px-6 py-10 text-center text-white sm:px-10">
            <div class="pointer-events-none absolute inset-0 opacity-30" style="background: radial-gradient(60% 120% at 100% 0%, var(--pp-accent), transparent 60%)"></div>
            <div class="relative">
                @if (! empty($props['eyebrow']))<p class="text-xs font-semibold uppercase tracking-widest text-white/70">{{ $props['eyebrow'] }}</p>@endif
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $props['heading'] ?? __('Limited-time offer') }}</h2>
                @if (! empty($props['sub']))<p class="mx-auto mt-2 max-w-lg text-sm text-white/80">{{ $props['sub'] }}</p>@endif
                <div class="mt-6">
                    @include('builder.blocks._buy', ['label' => $btn, 'class' => 'inline-block px-8 py-3.5 text-sm font-bold text-white shadow-lg transition hover:-translate-y-0.5'])
                </div>
            </div>
        </div>
    </div>
</section>
