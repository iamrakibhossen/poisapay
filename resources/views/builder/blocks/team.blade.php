@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
        <div class="mx-auto max-w-4xl px-5">
            @if (! empty($props['heading']))<h2 class="text-center text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] }}</h2>@endif
            @if (! empty($props['sub']))<p class="mx-auto mt-2 max-w-lg text-center text-sm text-neutral-500">{{ $props['sub'] }}</p>@endif
            <div class="mt-9 grid gap-6 sm:grid-cols-2 md:grid-cols-3">
                @foreach ($items as $m)
                    @php $m = is_array($m) ? $m : ['name' => $m]; @endphp
                    <div class="text-center">
                        @if (! empty($m['photo']))
                            <img src="{{ $m['photo'] }}" alt="{{ $m['name'] ?? '' }}" class="mx-auto h-24 w-24 rounded-full object-cover" loading="lazy" />
                        @else
                            <div class="mx-auto grid h-24 w-24 place-items-center rounded-full text-xl font-bold text-white" style="background: var(--pp-accent)">{{ mb_strtoupper(mb_substr($m['name'] ?? '?', 0, 1)) }}</div>
                        @endif
                        <p class="mt-3 text-sm font-bold text-neutral-900">{{ $m['name'] ?? '' }}</p>
                        @if (! empty($m['role']))<p class="text-xs text-neutral-500">{{ $m['role'] }}</p>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
