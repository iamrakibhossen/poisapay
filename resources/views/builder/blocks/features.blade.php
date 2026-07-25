@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
        <div class="mx-auto max-w-4xl px-5">
            @if (! empty($props['eyebrow']))<p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--pp-accent)">{{ $props['eyebrow'] }}</p>@endif
            <h2 class="mt-2 text-center text-2xl font-bold tracking-tight">{{ $props['heading'] ?? __('Everything you get') }}</h2>
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                @foreach ($items as $f)
                    <div class="flex items-start gap-3 rounded-2xl border border-neutral-200 bg-white p-5 transition hover:shadow-md">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-white" style="background: var(--pp-accent)"><x-heroicon-s-sparkles class="h-4 w-4" /></span>
                        <p class="pt-1 text-sm font-semibold text-neutral-800">{{ is_array($f) ? ($f['title'] ?? '') : $f }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
