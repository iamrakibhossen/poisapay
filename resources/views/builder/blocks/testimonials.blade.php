@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
        <div class="mx-auto max-w-4xl px-5">
            <p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--pp-accent)">{{ __('Reviews') }}</p>
            <h2 class="mt-2 text-center text-2xl font-bold tracking-tight">{{ $props['heading'] ?? __('Loved by buyers') }}</h2>
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                @foreach ($items as $t)
                    <figure class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                        <div class="text-sm text-amber-500">★★★★★</div>
                        <blockquote class="mt-2 text-sm leading-relaxed text-neutral-700">“{{ is_array($t) ? ($t['quote'] ?? '') : $t }}”</blockquote>
                        @if (is_array($t) && ! empty($t['name']))
                            <figcaption class="mt-3 flex items-center gap-2.5">
                                <span class="grid h-8 w-8 place-items-center rounded-full text-xs font-bold text-white" style="background: var(--pp-accent)">{{ mb_strtoupper(mb_substr($t['name'], 0, 1)) }}</span>
                                <span class="text-xs text-neutral-500"><span class="font-semibold text-neutral-800">{{ $t['name'] }}</span>@if (! empty($t['role'])) · {{ $t['role'] }}@endif</span>
                            </figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif
