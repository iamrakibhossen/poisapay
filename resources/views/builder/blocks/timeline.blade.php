@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
        <div class="mx-auto max-w-2xl px-5">
            @if (! empty($props['heading']))<h2 class="mb-8 text-center text-2xl font-bold tracking-tight">{{ $props['heading'] }}</h2>@endif
            <ol class="relative border-s-2 border-neutral-100">
                @foreach ($items as $it)
                    @php $it = is_array($it) ? $it : ['title' => $it]; @endphp
                    <li class="mb-8 ms-6 last:mb-0">
                        <span class="absolute -start-[9px] mt-1 h-4 w-4 rounded-full ring-4 ring-white" style="background: var(--pp-accent)"></span>
                        @if (! empty($it['date']))<p class="text-xs font-semibold uppercase tracking-wide" style="color: var(--pp-accent)">{{ $it['date'] }}</p>@endif
                        <h3 class="mt-0.5 text-sm font-bold text-neutral-900">{{ $it['title'] ?? '' }}</h3>
                        @if (! empty($it['desc']))<p class="mt-1 text-sm leading-relaxed text-neutral-500">{{ $it['desc'] }}</p>@endif
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif
