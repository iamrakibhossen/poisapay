@php $text = $props['text'] ?? ''; @endphp
@if ($text !== '')
    <section id="{{ $node->id }}" class="pp-block py-10">
        <div class="mx-auto max-w-2xl px-5">
            <div class="flex items-center gap-4 rounded-2xl border p-5" style="border-color: color-mix(in srgb, var(--pp-accent) 25%, transparent); background: color-mix(in srgb, var(--pp-accent) 5%, transparent)">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full text-white shadow-sm" style="background: var(--pp-accent)"><x-heroicon-s-shield-check class="h-6 w-6" /></span>
                <p class="text-sm font-medium text-neutral-700">{{ $text }}</p>
            </div>
        </div>
    </section>
@endif
