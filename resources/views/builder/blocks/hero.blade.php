@php
    // Hero — 5 layout variants (centered / split / minimal / gradient / showcase),
    // each with an optional dark background. Backward compatible: no variant → centered.
    $variants = ['centered', 'split', 'minimal', 'gradient', 'showcase'];
    $variant = in_array($props['variant'] ?? 'centered', $variants, true) ? $props['variant'] : 'centered';
    $gradient = $variant === 'gradient';
    $dark = (bool) ($props['dark'] ?? false) || $gradient;

    $p = $ctx->product;
    $headline = $props['headline'] ?? $p['name'] ?? '';
    $eyebrow = trim((string) ($props['eyebrow'] ?? ''));
    $btn = $props['btn'] ?? 'Buy now';
    $image = (string) ($props['image'] ?? '');
    $showRating = (bool) ($props['showRating'] ?? true);
    $showTrust = (bool) ($props['showTrust'] ?? true);

    $ink = $dark ? 'text-white' : 'text-neutral-900';
    $tag = $gradient ? 'text-white' : 'text-[color:var(--pp-accent)]';
    $muted = $dark ? 'text-white/75' : 'text-neutral-600';
    $subtle = $dark ? 'text-white/60' : 'text-neutral-500';
    $badge = $dark ? 'border-white/15 bg-white/10 text-white/85' : 'border-neutral-200 bg-white/70 text-neutral-600';

    $sectionStyle = $gradient
        ? 'background: linear-gradient(135deg, var(--pp-accent), color-mix(in srgb, var(--pp-accent) 50%, #000))'
        : '';
    $sectionClass = $gradient ? '' : ($dark ? 'bg-neutral-950' : '');
    // Primary button: white-on-accent when the surface is coloured/dark.
    $btnStyle = ($gradient || $dark)
        ? 'background:#fff; color: var(--pp-accent); border-radius: var(--pp-btn-radius)'
        : null;
    $btnCls = 'w-full px-8 py-4 text-sm font-semibold shadow-lg shadow-black/5 transition hover:-translate-y-0.5 hover:opacity-95 sm:w-auto '.(($gradient || $dark) ? '' : 'text-white');
@endphp

<section id="{{ $node->id }}" @class(['pp-block relative overflow-hidden', $sectionClass]) @if ($sectionStyle) style="{{ $sectionStyle }}" @endif>
    @if (! $dark)
        <div class="pointer-events-none absolute inset-x-0 top-0 h-72" style="background: radial-gradient(60% 100% at 50% 0%, var(--pp-accent), transparent 70%); opacity: .12"></div>
    @endif

    @switch($variant)
        {{-- ── Split: text left, image right ─────────────────────────────── --}}
        @case('split')
            <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-5 py-16 md:grid-cols-2 md:py-24">
                <div>
                    @if ($eyebrow)<p class="text-xs font-semibold uppercase tracking-[0.14em] {{ $tag }}">{{ $eyebrow }}</p>@endif
                    @if ($showRating)<div class="mb-3 mt-1 text-sm text-amber-500">★★★★★ <span class="{{ $subtle }}">4.9 · 200+ buyers</span></div>@endif
                    <h1 data-edit="headline" class="text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl {{ $ink }}">{{ $headline }}</h1>
                    @if (! empty($props['tagline']))<p data-edit="tagline" class="mt-4 text-lg font-medium {{ $tag }}">{{ $props['tagline'] }}</p>@endif
                    @if (! empty($props['desc']))<p data-edit="desc" class="mt-3 max-w-md text-sm leading-relaxed {{ $muted }}">{{ $props['desc'] }}</p>@endif
                    <div class="mt-8 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                        @include('builder.blocks._buy', ['label' => $btn, 'class' => $btnCls, 'style' => $btnStyle])
                        @if (! empty($p['price']))<span class="text-xl font-bold {{ $ink }}">{{ $p['price'] }}@if (! empty($p['comparePrice']))<span class="ms-1 text-base font-medium {{ $subtle }} line-through">{{ $p['comparePrice'] }}</span>@endif</span>@endif
                    </div>
                </div>
                <div class="relative">
                    @if ($image)
                        <x-builder.image :src="$image" :alt="$headline" sizes="(min-width: 768px) 50vw, 100vw" class="w-full rounded-3xl object-cover shadow-2xl ring-1 ring-black/5" />
                    @else
                        <div class="grid aspect-[4/3] w-full place-items-center rounded-3xl border border-dashed {{ $dark ? 'border-white/20 text-white/40' : 'border-neutral-200 text-neutral-300' }}"><x-heroicon-o-photo class="h-12 w-12" /></div>
                    @endif
                </div>
            </div>
            @break

        {{-- ── Minimal: tight, no chrome ──────────────────────────────────── --}}
        @case('minimal')
            <div class="relative mx-auto max-w-2xl px-5 py-16 text-center sm:py-24">
                <h1 data-edit="headline" class="text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl {{ $ink }}">{{ $headline }}</h1>
                @if (! empty($props['desc']))<p data-edit="desc" class="mx-auto mt-4 max-w-lg text-base leading-relaxed {{ $muted }}">{{ $props['desc'] }}</p>@endif
                <div class="mt-8">@include('builder.blocks._buy', ['label' => $btn, 'class' => 'px-8 py-4 text-sm font-semibold shadow-lg transition hover:-translate-y-0.5 '.(($gradient || $dark) ? '' : 'text-white'), 'style' => $btnStyle])</div>
            </div>
            @break

        {{-- ── Showcase: centered copy above a big image ──────────────────── --}}
        @case('showcase')
            <div class="relative mx-auto max-w-4xl px-5 pt-16 text-center sm:pt-24">
                @if ($eyebrow)<p class="text-xs font-semibold uppercase tracking-[0.14em] {{ $tag }}">{{ $eyebrow }}</p>@endif
                <h1 data-edit="headline" class="mx-auto mt-3 max-w-3xl text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-6xl {{ $ink }}">{{ $headline }}</h1>
                @if (! empty($props['desc']))<p data-edit="desc" class="mx-auto mt-4 max-w-xl text-base leading-relaxed {{ $muted }}">{{ $props['desc'] }}</p>@endif
                <div class="mt-8 flex justify-center">@include('builder.blocks._buy', ['label' => $btn, 'class' => 'px-8 py-4 text-sm font-semibold shadow-lg transition hover:-translate-y-0.5 '.(($gradient || $dark) ? '' : 'text-white'), 'style' => $btnStyle])</div>
            </div>
            <div class="relative mx-auto mt-12 max-w-5xl px-5 pb-4">
                @if ($image)
                    <x-builder.image :src="$image" :alt="$headline" sizes="(min-width: 1024px) 1024px, 100vw" class="w-full rounded-t-3xl object-cover shadow-2xl ring-1 ring-black/5" />
                @else
                    <div class="grid aspect-[16/9] w-full place-items-center rounded-t-3xl border border-dashed {{ $dark ? 'border-white/20 text-white/40' : 'border-neutral-200 text-neutral-300' }}"><x-heroicon-o-photo class="h-12 w-12" /></div>
                @endif
            </div>
            @break

        {{-- ── Centered / Gradient (default) ──────────────────────────────── --}}
        @default
            <div class="relative mx-auto max-w-3xl px-5 py-14 text-center sm:py-20">
                @if ($eyebrow)
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] {{ $tag }}">{{ $eyebrow }}</p>
                @elseif ($showRating)
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-medium shadow-sm {{ $badge }}"><span class="text-amber-500">★★★★★</span> 4.9 · {{ __('loved by 200+ buyers') }}</span>
                @endif
                <h1 data-edit="headline" class="mx-auto mt-5 max-w-2xl text-3xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl {{ $ink }}">{{ $headline }}</h1>
                @if (! empty($props['tagline']))<p data-edit="tagline" class="mx-auto mt-4 max-w-xl text-lg font-medium {{ $tag }}">{{ $props['tagline'] }}</p>@endif
                @if (! empty($props['desc']))<p data-edit="desc" class="mx-auto mt-3 max-w-xl text-sm leading-relaxed {{ $muted }}">{{ $props['desc'] }}</p>@endif

                <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    @include('builder.blocks._buy', ['label' => $btn, 'class' => $btnCls, 'style' => $btnStyle])
                    @if (! empty($p['price']))<span class="text-xl font-bold {{ $ink }}">{{ $p['price'] }}@if (! empty($p['comparePrice']))<span class="ms-1 text-base font-medium {{ $subtle }} line-through">{{ $p['comparePrice'] }}</span>@endif</span>@endif
                </div>

                @if ($showTrust)
                    <div class="mt-7 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs {{ $subtle }}">
                        <span class="inline-flex items-center gap-1.5"><x-heroicon-o-bolt class="h-4 w-4 {{ $gradient ? 'text-white' : 'text-[color:var(--pp-accent)]' }}" /> {{ __('Instant access') }}</span>
                        <span class="inline-flex items-center gap-1.5"><x-heroicon-o-lock-closed class="h-4 w-4 {{ $gradient ? 'text-white' : 'text-[color:var(--pp-accent)]' }}" /> {{ __('Secure checkout') }}</span>
                        <span class="inline-flex items-center gap-1.5"><x-heroicon-o-shield-check class="h-4 w-4 {{ $gradient ? 'text-white' : 'text-[color:var(--pp-accent)]' }}" /> {{ __('14-day money-back') }}</span>
                    </div>
                @endif
            </div>
    @endswitch
</section>
