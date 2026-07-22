@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white border border-gray-200 rounded-xl shadow-sm max-w-3xl mx-auto my-6']) }}>
    @if ($title || $description)
        <div class="px-6 py-5 border-b border-gray-100">
            @if ($title)
                <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
            @endif
            @if ($description)
                <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
            @endif
        </div>
    @endif
    <div class="p-6 sm:p-8">
        {{ $slot }}
    </div>
</div>
