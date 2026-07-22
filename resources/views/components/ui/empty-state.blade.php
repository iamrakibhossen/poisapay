@props([
    'icon' => 'inbox',
    'title' => 'Nothing here yet',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <span class="mb-4 grid h-14 w-14 place-items-center rounded-full bg-gray-100 text-gray-400">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-7 w-7" />
    </span>
    <h3 class="text-base font-semibold text-gray-800">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">{{ $action }}</div>
    @endisset
</div>
