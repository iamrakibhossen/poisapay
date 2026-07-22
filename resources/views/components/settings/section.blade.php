@props(['title' => '', 'description' => null])

{{-- Minimal settings section: title/description stacked above full-width content.
     Hairline divider on top. --}}
<section {{ $attributes->merge(['class' => 'border-t border-neutral-100 pt-6']) }}>
    <div class="mb-4">
        <h2 class="text-sm font-semibold text-neutral-900">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 text-sm text-neutral-500">{{ $description }}</p>
        @endif
    </div>
    <div>
        {{ $slot }}
    </div>
</section>
