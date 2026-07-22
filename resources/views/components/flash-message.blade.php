@props([
    'type' => 'success',
])

@php
    $flash = match ($type) {
        'success' => ['icon' => 'heroicon-o-check-circle', 'class' => 'text-green-500'],
        'danger', 'error' => ['icon' => 'heroicon-o-x-circle', 'class' => 'text-red-500'],
        'warning' => ['icon' => 'heroicon-o-exclamation-triangle', 'class' => 'text-amber-500'],
        default => ['icon' => 'heroicon-o-information-circle', 'class' => 'text-gray-600'],
    };
@endphp
<div x-data="{
        show: true,
        init() { setTimeout(() => this.show = false, 5000); }
    }"
    class="fixed bottom-8 right-8 z-[60]">
    <template x-if="show">
        <div x-transition.opacity
            class="group flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-5 py-4 shadow-xl transition hover:shadow-2xl">
            @svg($flash['icon'], ['class' => 'h-6 w-6 shrink-0 '.$flash['class']])
            <p class="text-sm font-medium text-gray-800">{{ $slot }}</p>
            <button type="button" @click="show = false"
                class="ms-2 rounded-full p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                <x-heroicon-o-x-mark class="h-4 w-4" />
            </button>
        </div>
    </template>
</div>
