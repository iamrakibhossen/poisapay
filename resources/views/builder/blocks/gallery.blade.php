@php $items = $props['items'] ?? []; $cols = (int) ($props['cols'] ?? 3); $cols = $cols >= 2 && $cols <= 5 ? $cols : 3; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block py-12">
        <div class="mx-auto max-w-5xl px-5">
            @if (! empty($props['heading']))<h2 class="mb-6 text-center text-2xl font-bold tracking-tight">{{ $props['heading'] }}</h2>@endif
            <div class="grid gap-3" style="grid-template-columns: repeat({{ $cols }}, minmax(0, 1fr))">
                @foreach ($items as $it)
                    @php $src = is_array($it) ? ($it['src'] ?? '') : $it; @endphp
                    @if ($src)<img src="{{ $src }}" alt="{{ is_array($it) ? ($it['alt'] ?? '') : '' }}" class="aspect-square w-full rounded-2xl object-cover" loading="lazy" />@endif
                @endforeach
            </div>
        </div>
    </section>
@endif
