@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-5 sm:p-6',
])

<div {{ $attributes->merge(['class' => 'pp-card '.$padding]) }}>
    @if ($title || isset($actions))
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                @if ($title)
                    <h3 class="text-base font-semibold text-neutral-900">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-neutral-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
