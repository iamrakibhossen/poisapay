@php
    // Normalise items → {src, alt, caption, category}, dropping empty rows.
    $items = collect($props['items'] ?? [])
        ->map(fn ($it) => is_array($it) ? $it : ['src' => $it])
        ->filter(fn ($it) => ! empty($it['src']))
        ->map(fn ($it) => [
            'src' => $it['src'],
            'alt' => $it['alt'] ?? '',
            'caption' => $it['caption'] ?? '',
            'category' => trim($it['category'] ?? ''),
        ])
        ->values();

    $cols = (int) ($props['cols'] ?? 3);
    $cols = $cols >= 2 && $cols <= 5 ? $cols : 3;
    $layout = in_array($props['layout'] ?? 'grid', ['grid', 'masonry', 'carousel'], true) ? $props['layout'] : 'grid';
    $lightbox = (bool) ($props['lightbox'] ?? true);
    $captions = (bool) ($props['captions'] ?? false);
    $filter = (bool) ($props['filter'] ?? false);
    $perLoad = (int) ($props['perLoad'] ?? 0);
    $cats = $filter ? $items->pluck('category')->filter()->unique()->values() : collect();

    $colClass = ['grid-cols-2', 'grid-cols-2 sm:grid-cols-3', 'grid-cols-2 sm:grid-cols-4', 'grid-cols-2 sm:grid-cols-5'][$cols - 2] ?? 'grid-cols-2 sm:grid-cols-3';
    $masonryClass = ['columns-2', 'columns-2 sm:columns-3', 'columns-2 sm:columns-4', 'columns-2 sm:columns-5'][$cols - 2] ?? 'columns-2 sm:columns-3';

    $lb = $items->map(fn ($it) => ['src' => $it['src'], 'cap' => $it['caption']])->values();
@endphp
@if ($items->isNotEmpty())
    <section id="{{ $node->id }}" class="pp-block py-12"
        x-data="{
            cat: 'all',
            limit: {{ $perLoad > 0 ? $perLoad : $items->count() }},
            lb: false, li: 0,
            imgs: @js($lb),
            open(i) { @if ($lightbox) this.li = i; this.lb = true; document.body.style.overflow = 'hidden'; @endif },
            close() { this.lb = false; document.body.style.overflow = ''; },
            next() { this.li = (this.li + 1) % this.imgs.length; },
            prev() { this.li = (this.li - 1 + this.imgs.length) % this.imgs.length; },
            shown(i, c) { return (this.cat === 'all' || this.cat === c) && i < this.limit; }
        }"
        @keydown.window.escape="close()" @keydown.window.arrow-right="lb && next()" @keydown.window.arrow-left="lb && prev()">
        <div class="mx-auto max-w-5xl px-5">
            @if (! empty($props['heading']))<h2 class="mb-6 text-center text-2xl font-bold tracking-tight">{{ $props['heading'] }}</h2>@endif

            @if ($filter && $cats->isNotEmpty())
                <div class="mb-6 flex flex-wrap justify-center gap-2">
                    <button type="button" @click="cat = 'all'" :class="cat === 'all' ? 'text-white' : 'text-neutral-600 hover:bg-neutral-100'" :style="cat === 'all' ? 'background: var(--pp-accent); border-color: transparent' : ''" class="rounded-full border border-neutral-200 px-3.5 py-1.5 text-xs font-semibold transition">{{ __('All') }}</button>
                    @foreach ($cats as $c)
                        <button type="button" @click="cat = @js($c)" :class="cat === @js($c) ? 'text-white' : 'text-neutral-600 hover:bg-neutral-100'" :style="cat === @js($c) ? 'background: var(--pp-accent); border-color: transparent' : ''" class="rounded-full border border-neutral-200 px-3.5 py-1.5 text-xs font-semibold transition">{{ $c }}</button>
                    @endforeach
                </div>
            @endif

            @if ($layout === 'carousel')
                <div class="-mx-5 flex snap-x snap-mandatory gap-3 overflow-x-auto px-5 pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($items as $i => $it)
                        <figure x-show="shown({{ $i }}, @js($it['category']))" class="w-[70%] shrink-0 snap-center sm:w-[45%] lg:w-[32%]">
                            <button type="button" @click="open({{ $i }})" class="block w-full overflow-hidden rounded-2xl {{ $lightbox ? 'cursor-zoom-in' : 'cursor-default' }}">
                                <x-builder.image :src="$it['src']" :alt="$it['alt'] ?: $it['caption']" sizes="70vw" class="aspect-[4/3] w-full object-cover transition duration-300 hover:scale-[1.03]" />
                            </button>
                            @if ($captions && $it['caption'])<figcaption class="mt-2 text-center text-xs text-neutral-500">{{ $it['caption'] }}</figcaption>@endif
                        </figure>
                    @endforeach
                </div>
            @elseif ($layout === 'masonry')
                <div class="{{ $masonryClass }} [column-gap:0.75rem]">
                    @foreach ($items as $i => $it)
                        <figure x-show="shown({{ $i }}, @js($it['category']))" class="mb-3 break-inside-avoid">
                            <button type="button" @click="open({{ $i }})" class="group relative block w-full overflow-hidden rounded-2xl {{ $lightbox ? 'cursor-zoom-in' : 'cursor-default' }}">
                                <x-builder.image :src="$it['src']" :alt="$it['alt'] ?: $it['caption']" sizes="(min-width: 640px) 33vw, 50vw" class="w-full object-cover transition duration-300 group-hover:scale-[1.03]" />
                                @if ($captions && $it['caption'])<figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-2 text-xs font-medium text-white opacity-0 transition group-hover:opacity-100">{{ $it['caption'] }}</figcaption>@endif
                            </button>
                        </figure>
                    @endforeach
                </div>
            @else
                <div class="grid {{ $colClass }} gap-3">
                    @foreach ($items as $i => $it)
                        <figure x-show="shown({{ $i }}, @js($it['category']))">
                            <button type="button" @click="open({{ $i }})" class="group relative block w-full overflow-hidden rounded-2xl {{ $lightbox ? 'cursor-zoom-in' : 'cursor-default' }}">
                                <x-builder.image :src="$it['src']" :alt="$it['alt'] ?: $it['caption']" sizes="(min-width: 640px) 33vw, 50vw" class="aspect-square w-full object-cover transition duration-300 group-hover:scale-[1.04]" />
                                @if ($captions && $it['caption'])<figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-2 text-xs font-medium text-white opacity-0 transition group-hover:opacity-100">{{ $it['caption'] }}</figcaption>@endif
                            </button>
                        </figure>
                    @endforeach
                </div>
            @endif

            @if ($perLoad > 0 && $items->count() > $perLoad)
                <div class="mt-6 text-center" x-show="limit < {{ $items->count() }}">
                    <button type="button" @click="limit += {{ $perLoad }}" class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 px-5 py-2 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">{{ __('Load more') }}</button>
                </div>
            @endif
        </div>

        @if ($lightbox)
            <div x-show="lb" x-cloak x-transition.opacity @click.self="close()" class="fixed inset-0 z-[70] grid place-items-center bg-black/90 p-4">
                <button type="button" @click="close()" class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                <button type="button" @click="prev()" x-show="imgs.length > 1" class="absolute left-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20"><x-heroicon-o-chevron-left class="h-6 w-6" /></button>
                <button type="button" @click="next()" x-show="imgs.length > 1" class="absolute right-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20"><x-heroicon-o-chevron-right class="h-6 w-6" /></button>
                <figure class="max-h-[86vh] max-w-5xl">
                    <img :src="imgs[li] && imgs[li].src" :alt="imgs[li] && imgs[li].cap" class="max-h-[80vh] w-auto rounded-lg object-contain" />
                    <figcaption x-show="imgs[li] && imgs[li].cap" class="mt-3 text-center text-sm text-white/80" x-text="imgs[li] && imgs[li].cap"></figcaption>
                </figure>
            </div>
        @endif
    </section>
@endif
