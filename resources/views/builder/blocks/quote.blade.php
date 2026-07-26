<section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-16">
    <figure class="mx-auto max-w-2xl px-5 text-center">
        <x-heroicon-s-chat-bubble-bottom-center-text class="mx-auto h-8 w-8" style="color: color-mix(in srgb, var(--pp-accent) 40%, transparent)" />
        <blockquote class="mt-4 text-xl font-medium leading-relaxed text-neutral-800 sm:text-2xl">“{{ $props['quote'] ?? __('This changed everything for my business.') }}”</blockquote>
        @if (! empty($props['name']))
            <figcaption class="mt-5 flex items-center justify-center gap-2.5">
                @if (! empty($props['photo']))<x-builder.image :src="$props['photo']" :alt="$props['name']" sizes="36px" class="h-9 w-9 rounded-full object-cover" />@endif
                <span class="text-sm"><span class="font-semibold text-neutral-900">{{ $props['name'] }}</span>@if (! empty($props['role']))<span class="text-neutral-400"> · {{ $props['role'] }}</span>@endif</span>
            </figcaption>
        @endif
    </figure>
</section>
