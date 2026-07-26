@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block py-12">
        <div class="mx-auto max-w-3xl px-5">
            @if (! empty($props['heading']))<h2 class="mb-6 text-center text-2xl font-bold tracking-tight">{{ $props['heading'] }}</h2>@endif
            <div class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                @foreach ($items as $it)
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full text-white" style="background: var(--pp-accent)"><x-heroicon-s-check class="h-3.5 w-3.5" /></span>
                        <p class="text-sm text-neutral-700">{{ is_array($it) ? ($it['text'] ?? '') : $it }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
