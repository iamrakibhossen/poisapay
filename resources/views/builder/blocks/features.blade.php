@php
    // Feature grid — 4 layout variants (cards / icon-top / icon-left / alternating)
    // + dark mode + column control. Items: {title, desc, icon}.
    $items = collect($props['items'] ?? [])
        ->map(fn ($f) => is_array($f) ? $f : ['title' => $f])
        ->filter(fn ($f) => trim((string) ($f['title'] ?? '')) !== '')
        ->values();

    $variants = ['cards', 'iconTop', 'iconLeft', 'alternating'];
    $variant = in_array($props['variant'] ?? 'cards', $variants, true) ? $props['variant'] : 'cards';
    $dark = (bool) ($props['dark'] ?? false);
    $cols = (int) ($props['cols'] ?? 2);
    $cols = $cols >= 2 && $cols <= 4 ? $cols : 2;
    $colClass = [2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-2 lg:grid-cols-3', 4 => 'sm:grid-cols-2 lg:grid-cols-4'][$cols];

    $sec = $dark ? 'bg-neutral-950' : 'border-t border-neutral-100';
    $ink = $dark ? 'text-white' : 'text-neutral-900';
    $muted = $dark ? 'text-white/70' : 'text-neutral-600';
    $card = $dark ? 'border-white/10 bg-white/[0.04]' : 'border-neutral-200 bg-white';
    $badge = 'grid shrink-0 place-items-center rounded-xl text-white';
@endphp
@if ($items->isNotEmpty())
    <section id="{{ $node->id }}" class="pp-block {{ $sec }} py-16">
        <div class="mx-auto {{ $variant === 'alternating' ? 'max-w-4xl' : 'max-w-5xl' }} px-5">
            @if (! empty($props['eyebrow']))<p class="text-center text-xs font-semibold uppercase tracking-[0.14em] text-[color:var(--pp-accent)]">{{ $props['eyebrow'] }}</p>@endif
            <h2 class="mt-2 text-center text-2xl font-bold tracking-tight sm:text-3xl {{ $ink }}">{{ $props['heading'] ?? __('Everything you get') }}</h2>
            @if (! empty($props['sub']))<p class="mx-auto mt-3 max-w-xl text-center text-sm {{ $muted }}">{{ $props['sub'] }}</p>@endif

            @switch($variant)
                @case('iconTop')
                    <div class="mt-10 grid gap-6 {{ $colClass }}">
                        @foreach ($items as $f)
                            <div class="text-center">
                                <span class="mx-auto {{ $badge }} h-12 w-12" style="background: var(--pp-accent)"><x-builder.icon :name="$f['icon'] ?? 'sparkles'" class="h-5 w-5" /></span>
                                <h3 class="mt-4 text-sm font-bold {{ $ink }}">{{ $f['title'] ?? '' }}</h3>
                                @if (! empty($f['desc']))<p class="mx-auto mt-1.5 max-w-xs text-sm leading-relaxed {{ $muted }}">{{ $f['desc'] }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                    @break

                @case('iconLeft')
                    <div class="mt-10 grid gap-x-8 gap-y-6 {{ $colClass }}">
                        @foreach ($items as $f)
                            <div class="flex items-start gap-3.5">
                                <span class="{{ $badge }} h-10 w-10" style="background: color-mix(in srgb, var(--pp-accent) 14%, transparent); color: var(--pp-accent)"><x-builder.icon :name="$f['icon'] ?? 'sparkles'" class="h-5 w-5" /></span>
                                <div>
                                    <h3 class="text-sm font-bold {{ $ink }}">{{ $f['title'] ?? '' }}</h3>
                                    @if (! empty($f['desc']))<p class="mt-1 text-sm leading-relaxed {{ $muted }}">{{ $f['desc'] }}</p>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @break

                @case('alternating')
                    <div class="mt-10 space-y-4">
                        @foreach ($items as $i => $f)
                            <div class="flex items-center gap-5 rounded-2xl border {{ $card }} p-5 sm:p-6 {{ $i % 2 ? 'sm:flex-row-reverse' : '' }}">
                                <span class="{{ $badge }} h-12 w-12" style="background: var(--pp-accent)"><x-builder.icon :name="$f['icon'] ?? 'sparkles'" class="h-6 w-6" /></span>
                                <div class="{{ $i % 2 ? 'sm:text-right' : '' }}">
                                    <h3 class="text-base font-bold {{ $ink }}">{{ $f['title'] ?? '' }}</h3>
                                    @if (! empty($f['desc']))<p class="mt-1 text-sm leading-relaxed {{ $muted }}">{{ $f['desc'] }}</p>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @break

                @default
                    <div class="mt-10 grid gap-4 {{ $colClass }}">
                        @foreach ($items as $f)
                            <div class="flex items-start gap-3 rounded-2xl border {{ $card }} p-5 transition hover:-translate-y-0.5 hover:shadow-lg">
                                <span class="{{ $badge }} h-9 w-9" style="background: var(--pp-accent)"><x-builder.icon :name="$f['icon'] ?? 'sparkles'" class="h-4 w-4" /></span>
                                <div class="pt-0.5">
                                    <h3 class="text-sm font-bold {{ $ink }}">{{ $f['title'] ?? '' }}</h3>
                                    @if (! empty($f['desc']))<p class="mt-1 text-sm leading-relaxed {{ $muted }}">{{ $f['desc'] }}</p>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
            @endswitch
        </div>
    </section>
@endif
