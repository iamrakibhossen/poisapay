@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 bg-neutral-50/60 py-14">
        <div class="mx-auto max-w-md px-5">
            <p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--pp-accent)">{{ __('Included') }}</p>
            <h2 class="mt-2 text-center text-2xl font-bold tracking-tight">{{ $props['heading'] ?? __('Everything you get today') }}</h2>
            <div class="mt-6 divide-y divide-neutral-100 rounded-2xl border border-neutral-200 bg-white shadow-sm">
                @foreach ($items as $b)
                    <div class="flex items-center justify-between px-5 py-4 text-sm">
                        <span class="flex items-center gap-2.5 font-medium text-neutral-800"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full text-[10px] text-white" style="background: var(--pp-accent)">✓</span>{{ is_array($b) ? ($b['title'] ?? '') : $b }}</span>
                        @if (is_array($b) && ! empty($b['value']))<span class="font-semibold text-neutral-400 line-through">{{ $b['value'] }}</span>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
