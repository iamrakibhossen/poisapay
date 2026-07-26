@php
    $items = $ctx->catalog ?? [];
    $limit = (int) ($props['limit'] ?? 6);
    if ($limit > 0) {
        $items = array_slice($items, 0, $limit);
    }
    $cols = (int) ($props['cols'] ?? 3);
    $cols = $cols >= 2 && $cols <= 4 ? $cols : 3;
    $showPrice = $props['showPrice'] ?? true;
    $showSummary = $props['showSummary'] ?? true;
    $cta = $props['cta'] ?? __('View');
@endphp
@if (! empty($items) || $ctx->editing)
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
        {{-- Responsive column count, scoped to this block. --}}
        <style>#{{ $node->id }} .pp-pg{display:grid;gap:1.25rem;grid-template-columns:repeat({{ $cols }},minmax(0,1fr))}@media(max-width:640px){#{{ $node->id }} .pp-pg{grid-template-columns:repeat({{ min($cols, 2) }},minmax(0,1fr))}}</style>
        <div class="mx-auto max-w-5xl px-5">
            @if (! empty($props['heading']))<h2 class="text-center text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] }}</h2>@endif
            @if (! empty($props['sub']))<p class="mx-auto mt-2 max-w-lg text-center text-sm text-neutral-500">{{ $props['sub'] }}</p>@endif

            @if (empty($items))
                <p class="mt-8 rounded-2xl border border-dashed border-neutral-200 py-10 text-center text-sm text-neutral-400">{{ __('Your published products will appear here.') }}</p>
            @else
                <div class="pp-pg mt-9">
                    @foreach ($items as $p)
                        <article class="group flex flex-col overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                            <div class="aspect-[4/3] w-full overflow-hidden bg-neutral-50">
                                @if (! empty($p['image']))
                                    <img src="{{ $p['image'] }}" alt="{{ $p['name'] ?? '' }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]" loading="lazy" />
                                @else
                                    <div class="grid h-full place-items-center text-neutral-300"><x-heroicon-o-photo class="h-9 w-9" /></div>
                                @endif
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <h3 class="text-sm font-bold text-neutral-900">{{ $p['name'] ?? '' }}</h3>
                                @if ($showSummary && ! empty($p['summary']))<p class="mt-1 line-clamp-2 text-sm text-neutral-500">{{ $p['summary'] }}</p>@endif
                                <div class="mt-4 flex items-center justify-between gap-3 pt-1">
                                    @if ($showPrice && ! empty($p['price']))
                                        <span class="text-base font-bold text-neutral-900">{{ $p['price'] }}@if (! empty($p['comparePrice']))<span class="ms-1 text-xs font-medium text-neutral-400 line-through">{{ $p['comparePrice'] }}</span>@endif</span>
                                    @else
                                        <span></span>
                                    @endif
                                    <a href="{{ $ctx->editing ? '#' : ($p['url'] ?? '#') }}" class="shrink-0 px-3.5 py-2 text-xs font-semibold text-white transition hover:opacity-90" style="background: var(--pp-accent); border-radius: var(--pp-btn-radius)">{{ $cta }}</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
