@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
        <div class="mx-auto max-w-4xl px-5">
            <p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--pp-accent)">{{ __('How it works') }}</p>
            <h2 class="mt-2 text-center text-2xl font-bold tracking-tight">{{ $props['heading'] ?? __('Up and running in minutes') }}</h2>
            <div class="mt-10 grid gap-8 sm:grid-cols-3">
                @foreach ($items as $i => $s)
                    <div class="relative text-center">
                        <span class="mx-auto grid h-12 w-12 place-items-center rounded-full text-base font-bold text-white shadow-sm" style="background: var(--pp-accent)">{{ $i + 1 }}</span>
                        <p class="mt-4 text-base font-semibold text-neutral-900">{{ is_array($s) ? ($s['title'] ?? '') : $s }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ is_array($s) ? ($s['desc'] ?? '') : '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
