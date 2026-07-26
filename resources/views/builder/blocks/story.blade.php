@php $img = $props['image'] ?? ''; @endphp
<section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
    <div class="mx-auto grid max-w-4xl items-center gap-8 px-5 md:grid-cols-2">
        <div class="{{ ($props['imageSide'] ?? 'left') === 'right' ? 'md:order-2' : '' }}">
            @if ($img)
                <img src="{{ $img }}" alt="{{ $props['heading'] ?? '' }}" class="w-full rounded-3xl object-cover shadow-sm" loading="lazy" />
            @else
                <div class="grid aspect-square place-items-center rounded-3xl bg-neutral-50 text-neutral-300"><x-heroicon-o-photo class="h-10 w-10" /></div>
            @endif
        </div>
        <div>
            @if (! empty($props['eyebrow']))<p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--pp-accent)">{{ $props['eyebrow'] }}</p>@endif
            <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] ?? __('Our story') }}</h2>
            <div class="mt-3 space-y-3 text-sm leading-relaxed text-neutral-600">{!! nl2br(e($props['body'] ?? '')) !!}</div>
            @if (! empty($props['signature']))<p class="mt-4 text-sm font-semibold text-neutral-900">{{ $props['signature'] }}</p>@endif
        </div>
    </div>
</section>
