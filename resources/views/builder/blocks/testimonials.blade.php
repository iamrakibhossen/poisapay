@php
    // Testimonials — 4 variants (cards / carousel / minimal / single) + avatars + dark.
    $items = collect($props['items'] ?? [])
        ->map(fn ($t) => is_array($t) ? $t : ['quote' => $t])
        ->filter(fn ($t) => trim((string) ($t['quote'] ?? '')) !== '')
        ->values();

    $variants = ['cards', 'carousel', 'minimal', 'single'];
    $variant = in_array($props['variant'] ?? 'cards', $variants, true) ? $props['variant'] : 'cards';
    $dark = (bool) ($props['dark'] ?? false);
    $cols = (int) ($props['cols'] ?? 2);
    $cols = $cols >= 1 && $cols <= 3 ? $cols : 2;
    $colClass = [1 => '', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-2 lg:grid-cols-3'][$cols];
    $eyebrow = trim((string) ($props['eyebrow'] ?? 'Reviews'));

    $sec = $dark ? 'bg-neutral-950' : 'border-t border-neutral-100';
    $ink = $dark ? 'text-white' : 'text-neutral-900';
    $quote = $dark ? 'text-white/85' : 'text-neutral-700';
    $muted = $dark ? 'text-white/60' : 'text-neutral-500';
    $card = $dark ? 'border-white/10 bg-white/[0.04]' : 'border-neutral-200 bg-white shadow-sm';
@endphp
@if ($items->isNotEmpty())
    <section id="{{ $node->id }}" class="pp-block {{ $sec }} py-16">
        <div class="mx-auto max-w-5xl px-5">
            @if ($variant !== 'single')
                @if ($eyebrow)<p class="text-center text-xs font-semibold uppercase tracking-[0.14em] text-[color:var(--pp-accent)]">{{ $eyebrow }}</p>@endif
                <h2 class="mt-2 text-center text-2xl font-bold tracking-tight sm:text-3xl {{ $ink }}">{{ $props['heading'] ?? __('Loved by buyers') }}</h2>
            @endif

            @switch($variant)
                {{-- ── Single featured quote ──────────────────────────────────── --}}
                @case('single')
                    @php $t = $items->first(); @endphp
                    <figure class="mx-auto max-w-3xl text-center">
                        <div class="text-lg text-amber-500">★★★★★</div>
                        <blockquote class="mt-4 text-2xl font-medium leading-snug tracking-tight sm:text-3xl {{ $ink }}">“{{ $t['quote'] ?? '' }}”</blockquote>
                        @if (! empty($t['name']))
                            <figcaption class="mt-6 flex items-center justify-center gap-3">
                                @if (! empty($t['photo']))<x-builder.image :src="$t['photo']" :alt="$t['name']" sizes="48px" class="h-12 w-12 rounded-full object-cover" />
                                @else<span class="grid h-12 w-12 place-items-center rounded-full text-sm font-bold text-white" style="background: var(--pp-accent)">{{ mb_strtoupper(mb_substr($t['name'], 0, 1)) }}</span>@endif
                                <span class="text-left text-sm {{ $muted }}"><span class="block font-semibold {{ $ink }}">{{ $t['name'] }}</span>{{ $t['role'] ?? '' }}</span>
                            </figcaption>
                        @endif
                    </figure>
                    @break

                {{-- ── Horizontal carousel ────────────────────────────────────── --}}
                @case('carousel')
                    <div class="-mx-5 mt-9 flex snap-x snap-mandatory gap-4 overflow-x-auto px-5 pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($items as $t)
                            <figure class="w-[85%] shrink-0 snap-center rounded-2xl border {{ $card }} p-6 sm:w-[46%] lg:w-[31%]">
                                <div class="text-sm text-amber-500">★★★★★</div>
                                <blockquote class="mt-2 text-sm leading-relaxed {{ $quote }}">“{{ $t['quote'] ?? '' }}”</blockquote>
                                @if (! empty($t['name']))
                                    <figcaption class="mt-4 flex items-center gap-2.5">
                                        @if (! empty($t['photo']))<x-builder.image :src="$t['photo']" :alt="$t['name']" sizes="36px" class="h-9 w-9 rounded-full object-cover" />
                                        @else<span class="grid h-9 w-9 place-items-center rounded-full text-xs font-bold text-white" style="background: var(--pp-accent)">{{ mb_strtoupper(mb_substr($t['name'], 0, 1)) }}</span>@endif
                                        <span class="text-xs {{ $muted }}"><span class="font-semibold {{ $ink }}">{{ $t['name'] }}</span>@if (! empty($t['role'])) · {{ $t['role'] }}@endif</span>
                                    </figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                    @break

                {{-- ── Minimal (borderless) ───────────────────────────────────── --}}
                @case('minimal')
                    <div class="mt-10 grid gap-x-10 gap-y-8 {{ $colClass }}">
                        @foreach ($items as $t)
                            <figure>
                                <div class="text-sm text-amber-500">★★★★★</div>
                                <blockquote class="mt-2 text-base leading-relaxed {{ $quote }}">“{{ $t['quote'] ?? '' }}”</blockquote>
                                @if (! empty($t['name']))
                                    <figcaption class="mt-3 flex items-center gap-2.5">
                                        @if (! empty($t['photo']))<x-builder.image :src="$t['photo']" :alt="$t['name']" sizes="32px" class="h-8 w-8 rounded-full object-cover" />@endif
                                        <span class="text-xs {{ $muted }}"><span class="font-semibold {{ $ink }}">{{ $t['name'] }}</span>@if (! empty($t['role'])) · {{ $t['role'] }}@endif</span>
                                    </figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                    @break

                {{-- ── Cards (default) ────────────────────────────────────────── --}}
                @default
                    <div class="mt-9 grid gap-4 {{ $colClass }}">
                        @foreach ($items as $t)
                            <figure class="rounded-2xl border {{ $card }} p-5">
                                <div class="text-sm text-amber-500">★★★★★</div>
                                <blockquote class="mt-2 text-sm leading-relaxed {{ $quote }}">“{{ $t['quote'] ?? '' }}”</blockquote>
                                @if (! empty($t['name']))
                                    <figcaption class="mt-3 flex items-center gap-2.5">
                                        @if (! empty($t['photo']))<x-builder.image :src="$t['photo']" :alt="$t['name']" sizes="32px" class="h-8 w-8 rounded-full object-cover" />
                                        @else<span class="grid h-8 w-8 place-items-center rounded-full text-xs font-bold text-white" style="background: var(--pp-accent)">{{ mb_strtoupper(mb_substr($t['name'], 0, 1)) }}</span>@endif
                                        <span class="text-xs {{ $muted }}"><span class="font-semibold {{ $ink }}">{{ $t['name'] }}</span>@if (! empty($t['role'])) · {{ $t['role'] }}@endif</span>
                                    </figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
            @endswitch
        </div>
    </section>
@endif
