@props(['title' => '', 'subtitle' => null])

{{-- DollarHub page header: title + optional subtitle, actions slot on the right. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3 py-4']) }}>
    <div>
        <h1 class="flex items-center gap-4 text-2xl font-semibold text-gray-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
