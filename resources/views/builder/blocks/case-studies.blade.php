@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
        <div class="mx-auto max-w-4xl px-5">
            @if (! empty($props['heading']))<h2 class="text-center text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] }}</h2>@endif
            <div class="mt-8 grid gap-5 sm:grid-cols-2">
                @foreach ($items as $c)
                    @php $c = is_array($c) ? $c : ['title' => $c]; @endphp
                    <article class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm">
                        @if (! empty($c['metric']))<p class="text-3xl font-extrabold tracking-tight" style="color: var(--pp-accent)">{{ $c['metric'] }}</p>@endif
                        <h3 class="mt-2 text-sm font-bold text-neutral-900">{{ $c['title'] ?? '' }}</h3>
                        @if (! empty($c['body']))<p class="mt-1.5 text-sm leading-relaxed text-neutral-500">{{ $c['body'] }}</p>@endif
                        @if (! empty($c['name']))<p class="mt-3 text-xs font-medium text-neutral-400">— {{ $c['name'] }}</p>@endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
