<section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14 text-center">
    <div class="mx-auto max-w-2xl px-5">
        <h2 class="text-2xl font-bold tracking-tight">{{ $props['title'] ?? __('Watch the demo') }}</h2>
        @if (! empty($props['subtitle']))<p class="mt-1 text-sm text-neutral-500">{{ $props['subtitle'] }}</p>@endif
        <div class="group relative mt-6 grid aspect-video w-full place-items-center overflow-hidden rounded-2xl bg-neutral-900 shadow-lg">
            <div class="absolute inset-0 opacity-30" style="background: radial-gradient(60% 80% at 50% 50%, var(--pp-accent), transparent)"></div>
            <span class="relative grid h-16 w-16 place-items-center rounded-full bg-white/95 text-neutral-900 shadow-lg transition group-hover:scale-110"><x-heroicon-s-play class="h-7 w-7" /></span>
        </div>
    </div>
</section>
