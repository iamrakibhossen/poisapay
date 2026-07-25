@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-10">
        <div class="mx-auto grid max-w-4xl grid-cols-2 gap-6 px-5 sm:grid-cols-4">
            @foreach ($items as $s)
                <div class="text-center">
                    <p class="text-2xl font-extrabold sm:text-3xl" style="color: var(--pp-accent)">{{ is_array($s) ? ($s['value'] ?? '') : $s }}</p>
                    <p class="mt-0.5 text-[11px] uppercase tracking-wide text-neutral-400">{{ is_array($s) ? ($s['label'] ?? '') : '' }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
