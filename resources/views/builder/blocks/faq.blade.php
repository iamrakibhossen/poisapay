@php
    // FAQ — 3 variants (accordion / cards / two-column) + dark mode.
    $items = collect($props['items'] ?? [])
        ->map(fn ($q) => is_array($q) ? $q : ['q' => $q, 'a' => ''])
        ->filter(fn ($q) => trim((string) ($q['q'] ?? '')) !== '')
        ->values();

    $variants = ['accordion', 'cards', 'split'];
    $variant = in_array($props['variant'] ?? 'accordion', $variants, true) ? $props['variant'] : 'accordion';
    $dark = (bool) ($props['dark'] ?? false);
    $eyebrow = trim((string) ($props['eyebrow'] ?? 'FAQ'));
    $heading = $props['heading'] ?? __('Questions & answers');
    $sub = trim((string) ($props['sub'] ?? ''));

    $sec = $dark ? 'bg-neutral-950' : 'border-t border-neutral-100';
    $ink = $dark ? 'text-white' : 'text-neutral-900';
    $muted = $dark ? 'text-white/70' : 'text-neutral-500';
    $q = $dark ? 'text-white' : 'text-neutral-800';
    $card = $dark ? 'border-white/10 bg-white/[0.04]' : 'border-neutral-200 bg-white';
    $chev = $dark ? 'text-white/50' : 'text-neutral-400';
@endphp
@if ($items->isNotEmpty())
    <section id="{{ $node->id }}" class="pp-block {{ $sec }} py-16">
        @if ($variant === 'split')
            <div class="mx-auto grid max-w-5xl gap-10 px-5 md:grid-cols-[0.8fr_1.2fr]" x-data="{ open: 0 }">
                <div>
                    @if ($eyebrow)<p class="text-xs font-semibold uppercase tracking-[0.14em] text-[color:var(--pp-accent)]">{{ $eyebrow }}</p>@endif
                    <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl {{ $ink }}">{{ $heading }}</h2>
                    @if ($sub)<p class="mt-3 text-sm {{ $muted }}">{{ $sub }}</p>@endif
                </div>
                <div class="space-y-3">
                    @foreach ($items as $i => $it)
                        <div class="overflow-hidden rounded-2xl border {{ $card }}">
                            <button type="button" x-on:click="open = (open === {{ $i }} ? null : {{ $i }})" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left">
                                <span class="text-sm font-semibold {{ $q }}">{{ $it['q'] ?? '' }}</span>
                                <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 {{ $chev }} transition" x-bind:class="open === {{ $i }} && 'rotate-180'" />
                            </button>
                            @if (! empty($it['a']))
                                <div x-show="open === {{ $i }}" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                    <p class="px-5 pb-4 text-sm leading-relaxed {{ $muted }}">{{ $it['a'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif ($variant === 'cards')
            <div class="mx-auto max-w-5xl px-5">
                @if ($eyebrow)<p class="text-center text-xs font-semibold uppercase tracking-[0.14em] text-[color:var(--pp-accent)]">{{ $eyebrow }}</p>@endif
                <h2 class="mt-2 text-center text-2xl font-bold tracking-tight sm:text-3xl {{ $ink }}">{{ $heading }}</h2>
                @if ($sub)<p class="mx-auto mt-3 max-w-xl text-center text-sm {{ $muted }}">{{ $sub }}</p>@endif
                <div class="mt-9 grid gap-4 sm:grid-cols-2">
                    @foreach ($items as $it)
                        <div class="rounded-2xl border {{ $card }} p-5">
                            <div class="flex items-start gap-2.5">
                                <x-heroicon-s-question-mark-circle class="mt-0.5 h-5 w-5 shrink-0 text-[color:var(--pp-accent)]" />
                                <h3 class="text-sm font-bold {{ $q }}">{{ $it['q'] ?? '' }}</h3>
                            </div>
                            @if (! empty($it['a']))<p class="mt-2 pl-7 text-sm leading-relaxed {{ $muted }}">{{ $it['a'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="mx-auto max-w-2xl px-5" x-data="{ open: 0 }">
                @if ($eyebrow)<p class="text-center text-xs font-semibold uppercase tracking-[0.14em] text-[color:var(--pp-accent)]">{{ $eyebrow }}</p>@endif
                <h2 class="mt-2 text-center text-2xl font-bold tracking-tight sm:text-3xl {{ $ink }}">{{ $heading }}</h2>
                @if ($sub)<p class="mx-auto mt-3 max-w-lg text-center text-sm {{ $muted }}">{{ $sub }}</p>@endif
                <div class="mt-8 space-y-3">
                    @foreach ($items as $i => $it)
                        <div class="overflow-hidden rounded-2xl border {{ $card }}">
                            <button type="button" x-on:click="open = (open === {{ $i }} ? null : {{ $i }})" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left">
                                <span class="text-sm font-semibold {{ $q }}">{{ $it['q'] ?? '' }}</span>
                                <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 {{ $chev }} transition" x-bind:class="open === {{ $i }} && 'rotate-180'" />
                            </button>
                            @if (! empty($it['a']))
                                <div x-show="open === {{ $i }}" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                    <p class="px-5 pb-4 text-sm leading-relaxed {{ $muted }}">{{ $it['a'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@endif
