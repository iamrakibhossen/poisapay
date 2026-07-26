@php
    // Pricing — 3 variants (cards / minimal / compact) + optional monthly/yearly
    // toggle + column control + dark mode.
    $tiers = collect($props['items'] ?? [])->map(fn ($t) => is_array($t) ? $t : ['name' => $t])->values();

    $variants = ['cards', 'minimal', 'compact'];
    $variant = in_array($props['variant'] ?? 'cards', $variants, true) ? $props['variant'] : 'cards';
    $dark = (bool) ($props['dark'] ?? false);
    $cols = (int) ($props['cols'] ?? 3);
    $cols = $cols >= 2 && $cols <= 4 ? $cols : 3;
    $colClass = [2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-2 lg:grid-cols-3', 4 => 'sm:grid-cols-2 lg:grid-cols-4'][$cols];
    $toggle = (bool) ($props['billingToggle'] ?? false)
        && $tiers->contains(fn ($t) => trim((string) ($t['priceYearly'] ?? '')) !== '');

    $sec = $dark ? 'bg-neutral-950' : 'border-t border-neutral-100';
    $ink = $dark ? 'text-white' : 'text-neutral-900';
    $muted = $dark ? 'text-white/70' : 'text-neutral-500';
    $feat = $dark ? 'text-white/80' : 'text-neutral-600';
    $baseCard = $dark ? 'border-white/10 bg-white/[0.04]' : 'border-neutral-200 bg-white';
    $pad = $variant === 'compact' ? 'p-5' : 'p-6';
@endphp
@if ($tiers->isNotEmpty())
    <section id="{{ $node->id }}" class="pp-block {{ $sec }} py-16" @if ($toggle) x-data="{ annual: false }" @endif>
        <div class="mx-auto max-w-5xl px-5">
            @if (! empty($props['heading']))<h2 class="text-center text-2xl font-bold tracking-tight sm:text-3xl {{ $ink }}">{{ $props['heading'] }}</h2>@endif
            @if (! empty($props['sub']))<p class="mx-auto mt-2 max-w-lg text-center text-sm {{ $muted }}">{{ $props['sub'] }}</p>@endif

            @if ($toggle)
                <div class="mt-6 flex items-center justify-center gap-3 text-sm">
                    <span :class="!annual && 'font-semibold {{ $ink }}'" class="{{ $muted }}">{{ __('Monthly') }}</span>
                    <button type="button" role="switch" :aria-checked="annual" x-on:click="annual = !annual" class="relative h-6 w-11 shrink-0 rounded-full transition" :style="annual ? 'background: var(--pp-accent)' : '{{ $dark ? 'background: rgba(255,255,255,.2)' : 'background:#e5e7eb' }}'">
                        <span class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform" :class="annual && 'translate-x-5'"></span>
                    </button>
                    <span :class="annual && 'font-semibold {{ $ink }}'" class="{{ $muted }}">{{ __('Yearly') }}</span>
                    @if (! empty($props['yearlyNote']))<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">{{ $props['yearlyNote'] }}</span>@endif
                </div>
            @endif

            <div class="mt-9 grid gap-5 {{ $colClass }} {{ $variant === 'minimal' ? 'md:divide-x md:gap-0 '.($dark ? 'md:divide-white/10' : 'md:divide-neutral-200') : '' }}">
                @foreach ($tiers as $t)
                    @php
                        $featured = ! empty($t['featured']) && $t['featured'] !== 'false';
                        $feats = array_filter(array_map('trim', explode("\n", (string) ($t['features'] ?? ''))));
                        $hasYear = trim((string) ($t['priceYearly'] ?? '')) !== '';
                    @endphp
                    @if ($variant === 'minimal')
                        <div class="relative flex flex-col px-6 py-2 text-center">
                            @if ($featured)<span class="mx-auto mb-2 inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold text-white" style="background: var(--pp-accent)">{{ $t['badge'] ?? __('Most popular') }}</span>@endif
                            <p class="text-sm font-semibold {{ $ink }}">{{ $t['name'] ?? '' }}</p>
                            <p class="mt-3 text-4xl font-extrabold tracking-tight {{ $ink }}">
                                @if ($toggle && $hasYear)<span x-show="!annual">{{ $t['price'] ?? '' }}</span><span x-show="annual" x-cloak>{{ $t['priceYearly'] }}</span>@else{{ $t['price'] ?? '' }}@endif<span class="text-sm font-medium {{ $muted }}">{{ $t['period'] ?? '' }}</span>
                            </p>
                            @if (! empty($t['desc']))<p class="mt-2 text-sm {{ $muted }}">{{ $t['desc'] }}</p>@endif
                            @if ($feats)<ul class="mx-auto mt-5 space-y-2 text-left text-sm {{ $feat }}">@foreach ($feats as $f)<li class="flex items-start gap-2"><x-heroicon-s-check class="mt-0.5 h-4 w-4 shrink-0 text-[color:var(--pp-accent)]" /><span>{{ $f }}</span></li>@endforeach</ul>@endif
                            <div class="mt-auto pt-6">@include('builder.blocks._buy', ['label' => $t['cta'] ?? __('Choose plan'), 'class' => 'block w-full py-3 text-center text-sm font-semibold transition hover:opacity-95 '.($featured ? 'text-white' : 'text-[color:var(--pp-accent)]'), 'style' => $featured ? 'background: var(--pp-accent); border-radius: var(--pp-btn-radius)' : ($dark ? 'background: rgba(255,255,255,.08); border-radius: var(--pp-btn-radius)' : 'background:#f4f4f5; border-radius: var(--pp-btn-radius)')])</div>
                        </div>
                    @else
                        <div class="relative flex flex-col rounded-3xl border {{ $pad }} {{ $featured ? 'border-transparent shadow-xl ring-2 '.($dark ? 'bg-white/[0.06]' : 'bg-white') : $baseCard.' shadow-sm' }}" @if ($featured) style="--tw-ring-color: var(--pp-accent)" @endif>
                            @if ($featured)<span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full px-3 py-1 text-[11px] font-bold text-white" style="background: var(--pp-accent)">{{ $t['badge'] ?? __('Most popular') }}</span>@endif
                            <p class="text-sm font-semibold {{ $ink }}">{{ $t['name'] ?? '' }}</p>
                            <p class="mt-3 text-3xl font-extrabold tracking-tight {{ $ink }}">
                                @if ($toggle && $hasYear)<span x-show="!annual">{{ $t['price'] ?? '' }}</span><span x-show="annual" x-cloak>{{ $t['priceYearly'] }}</span>@else{{ $t['price'] ?? '' }}@endif<span class="text-sm font-medium {{ $muted }}">{{ $t['period'] ?? '' }}</span>
                            </p>
                            @if (! empty($t['desc']))<p class="mt-2 text-sm {{ $muted }}">{{ $t['desc'] }}</p>@endif
                            @if ($feats)<ul class="mt-5 space-y-2.5 text-sm {{ $feat }}">@foreach ($feats as $f)<li class="flex items-start gap-2"><x-heroicon-s-check class="mt-0.5 h-4 w-4 shrink-0 text-[color:var(--pp-accent)]" /><span>{{ $f }}</span></li>@endforeach</ul>@endif
                            <div class="mt-auto pt-6">@include('builder.blocks._buy', ['label' => $t['cta'] ?? __('Choose plan'), 'class' => 'block w-full py-3 text-center text-sm font-semibold text-white transition hover:opacity-95', 'style' => $featured ? 'background: var(--pp-accent); border-radius: var(--pp-btn-radius)' : 'background:#0f172a; border-radius: var(--pp-btn-radius)'])</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endif
