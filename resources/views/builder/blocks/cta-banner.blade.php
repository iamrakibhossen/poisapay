@php
    // CTA banner — 5 variants (gradient / simple / dark / floating card / split).
    $variants = ['gradient', 'simple', 'dark', 'card', 'split'];
    $variant = in_array($props['variant'] ?? 'gradient', $variants, true) ? $props['variant'] : 'gradient';
    $btn = $props['btn'] ?: __('Get started');
    $eyebrow = trim((string) ($props['eyebrow'] ?? ''));
    $heading = $props['heading'] ?? __('Ready to get started?');
    $sub = trim((string) ($props['sub'] ?? ''));
    $note = trim((string) ($props['note'] ?? ''));

    $onColour = in_array($variant, ['gradient', 'dark', 'card'], true); // white button + light text
    $whiteBtn = ['label' => $btn, 'class' => 'inline-block px-8 py-3.5 text-sm font-bold shadow-lg transition hover:-translate-y-0.5', 'style' => 'background:#fff; color: var(--pp-accent); border-radius: var(--pp-btn-radius)'];
    $accentBtn = ['label' => $btn, 'class' => 'inline-block px-8 py-3.5 text-sm font-bold text-white shadow-lg transition hover:-translate-y-0.5'];
@endphp

@switch($variant)
    @case('simple')
        <section id="{{ $node->id }}" class="pp-block border-y border-neutral-100 bg-neutral-50 py-14 text-center">
            <div class="mx-auto max-w-2xl px-5">
                @if ($eyebrow)<p class="text-xs font-semibold uppercase tracking-[0.14em] text-[color:var(--pp-accent)]">{{ $eyebrow }}</p>@endif
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-neutral-900 sm:text-3xl">{{ $heading }}</h2>
                @if ($sub)<p class="mx-auto mt-3 max-w-lg text-sm text-neutral-500">{{ $sub }}</p>@endif
                <div class="mt-7">@include('builder.blocks._buy', $accentBtn)</div>
                @if ($note)<p class="mt-3 text-xs text-neutral-400">{{ $note }}</p>@endif
            </div>
        </section>
        @break

    @case('dark')
        <section id="{{ $node->id }}" class="pp-block bg-neutral-950 py-16 text-center text-white">
            <div class="mx-auto max-w-2xl px-5">
                @if ($eyebrow)<p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/60">{{ $eyebrow }}</p>@endif
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-4xl">{{ $heading }}</h2>
                @if ($sub)<p class="mx-auto mt-3 max-w-lg text-sm text-white/70">{{ $sub }}</p>@endif
                <div class="mt-8">@include('builder.blocks._buy', $whiteBtn)</div>
                @if ($note)<p class="mt-3 text-xs text-white/50">{{ $note }}</p>@endif
            </div>
        </section>
        @break

    @case('card')
        <section id="{{ $node->id }}" class="pp-block bg-neutral-50 px-5 py-14">
            <div class="mx-auto max-w-4xl overflow-hidden rounded-3xl px-6 py-12 text-center text-white shadow-2xl sm:px-12" style="background: linear-gradient(135deg, var(--pp-accent), color-mix(in srgb, var(--pp-accent) 50%, #000))">
                @if ($eyebrow)<p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/70">{{ $eyebrow }}</p>@endif
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $heading }}</h2>
                @if ($sub)<p class="mx-auto mt-3 max-w-lg text-sm text-white/85">{{ $sub }}</p>@endif
                <div class="mt-7">@include('builder.blocks._buy', $whiteBtn)</div>
                @if ($note)<p class="mt-3 text-xs text-white/60">{{ $note }}</p>@endif
            </div>
        </section>
        @break

    @case('split')
        <section id="{{ $node->id }}" class="pp-block border-y border-neutral-100 py-12">
            <div class="mx-auto flex max-w-5xl flex-col items-center justify-between gap-6 px-5 text-center md:flex-row md:text-left">
                <div>
                    @if ($eyebrow)<p class="text-xs font-semibold uppercase tracking-[0.14em] text-[color:var(--pp-accent)]">{{ $eyebrow }}</p>@endif
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-neutral-900">{{ $heading }}</h2>
                    @if ($sub)<p class="mt-2 max-w-md text-sm text-neutral-500">{{ $sub }}</p>@endif
                </div>
                <div class="shrink-0">@include('builder.blocks._buy', $accentBtn)</div>
            </div>
        </section>
        @break

    @default
        <section id="{{ $node->id }}" class="pp-block py-16 text-center text-white" style="background: linear-gradient(135deg, var(--pp-accent), color-mix(in srgb, var(--pp-accent) 55%, #000))">
            <div class="mx-auto max-w-2xl px-5">
                @if ($eyebrow)<p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/70">{{ $eyebrow }}</p>@endif
                <h2 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $heading }}</h2>
                @if ($sub)<p class="mx-auto mt-3 max-w-lg text-sm text-white/85">{{ $sub }}</p>@endif
                <div class="mt-7">@include('builder.blocks._buy', $whiteBtn)</div>
                @if ($note)<p class="mt-3 text-xs text-white/60">{{ $note }}</p>@endif
            </div>
        </section>
@endswitch
