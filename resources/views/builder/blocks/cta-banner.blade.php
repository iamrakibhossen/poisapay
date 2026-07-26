@php $btn = $props['btn'] ?: __('Get started'); @endphp
<section id="{{ $node->id }}" class="pp-block py-14 text-center text-white" style="background: linear-gradient(135deg, var(--pp-accent), color-mix(in srgb, var(--pp-accent) 55%, #000))">
    <div class="mx-auto max-w-2xl px-5">
        <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $props['heading'] ?? __('Ready to get started?') }}</h2>
        @if (! empty($props['sub']))<p class="mx-auto mt-3 max-w-lg text-sm text-white/85">{{ $props['sub'] }}</p>@endif
        <div class="mt-7">
            @include('builder.blocks._buy', ['label' => $btn, 'class' => 'inline-block px-8 py-3.5 text-sm font-bold shadow-lg transition hover:-translate-y-0.5', 'style' => 'background:#fff; color: var(--pp-accent); border-radius: var(--pp-btn-radius)'])
        </div>
    </div>
</section>
