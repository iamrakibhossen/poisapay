@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14" style="background: color-mix(in srgb, var(--pp-accent) 4%, transparent)">
        <div class="mx-auto max-w-4xl px-5">
            @if (! empty($props['eyebrow']))<p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--pp-accent)">{{ $props['eyebrow'] }}</p>@endif
            <h2 class="mt-2 text-center text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] ?? __('Why you’ll love it') }}</h2>
            @if (! empty($props['subheading']))<p class="mx-auto mt-2 max-w-lg text-center text-sm text-neutral-500">{{ $props['subheading'] }}</p>@endif
            <div class="mt-9 grid gap-4 sm:grid-cols-2">
                @foreach ($items as $b)
                    @php $bTitle = is_array($b) ? ($b['title'] ?? '') : $b; $bDesc = is_array($b) ? ($b['desc'] ?? '') : ''; @endphp
                    <div class="group flex items-start gap-4 rounded-2xl border border-white bg-white/80 p-5 shadow-sm ring-1 ring-black/[0.03] backdrop-blur transition hover:-translate-y-0.5 hover:shadow-md">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-white shadow-sm transition group-hover:scale-105" style="background: var(--pp-accent)"><x-heroicon-s-check class="h-5 w-5" /></span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold leading-snug text-neutral-900">{{ $bTitle }}</p>
                            @if ($bDesc)<p class="mt-1 text-sm leading-relaxed text-neutral-500">{{ $bDesc }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
