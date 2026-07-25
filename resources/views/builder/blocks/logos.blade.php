@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 bg-neutral-50/60 py-8">
        <p class="text-center text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ $props['heading'] ?? __('Trusted by teams at') }}</p>
        <div class="mx-auto mt-4 flex max-w-4xl flex-wrap items-center justify-center gap-x-10 gap-y-3 px-5">
            @foreach ($items as $l)
                <span class="text-base font-bold text-neutral-400">{{ is_array($l) ? ($l['name'] ?? implode(' ', $l)) : $l }}</span>
            @endforeach
        </div>
    </section>
@endif
