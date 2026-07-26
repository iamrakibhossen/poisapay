@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block py-12" x-data="{ i: 0, n: {{ count($items) }} }">
        <div class="mx-auto max-w-3xl px-5">
            @if (! empty($props['heading']))<h2 class="mb-6 text-center text-2xl font-bold tracking-tight">{{ $props['heading'] }}</h2>@endif
            <div class="relative overflow-hidden rounded-3xl border border-neutral-200 bg-neutral-50">
                <div class="flex transition-transform duration-300" :style="`transform: translateX(-${i * 100}%)`">
                    @foreach ($items as $it)
                        @php $src = is_array($it) ? ($it['src'] ?? '') : $it; $cap = is_array($it) ? ($it['caption'] ?? '') : ''; @endphp
                        <div class="w-full shrink-0">
                            @if ($src)<x-builder.image :src="$src" :alt="$cap" sizes="(min-width: 768px) 768px, 100vw" class="aspect-video w-full object-cover" />@endif
                            @if ($cap)<p class="px-4 py-3 text-center text-sm text-neutral-500">{{ $cap }}</p>@endif
                        </div>
                    @endforeach
                </div>
                <button type="button" x-on:click="i = (i - 1 + n) % n" class="absolute left-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 shadow"><x-heroicon-o-chevron-left class="h-4 w-4" /></button>
                <button type="button" x-on:click="i = (i + 1) % n" class="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 shadow"><x-heroicon-o-chevron-right class="h-4 w-4" /></button>
            </div>
            <div class="mt-3 flex justify-center gap-1.5">
                @foreach ($items as $k => $it)
                    <button type="button" x-on:click="i = {{ $k }}" :class="i === {{ $k }} ? '' : 'bg-neutral-300'" :style="i === {{ $k }} ? 'background: var(--pp-accent)' : ''" class="h-2 w-2 rounded-full transition"></button>
                @endforeach
            </div>
        </div>
    </section>
@endif
