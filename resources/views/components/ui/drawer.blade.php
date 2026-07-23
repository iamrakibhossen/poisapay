@props([
    'name',
    'title' => null,
    'width' => 'md',   // sm | md | lg
])

@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg'];
    $w = $widths[$width] ?? $widths['md'];
@endphp

{{-- Right-slide drawer. Open/close with:
     $dispatch('open-drawer', '{{ $name }}') / $dispatch('close-drawer', '{{ $name }}'). --}}
<div
    x-data="{ open: false }"
    x-on:open-drawer.window="$event.detail === '{{ $name }}' && (open = true)"
    x-on:close-drawer.window="$event.detail === '{{ $name }}' && (open = false)"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50"
    style="display: none;"
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" x-on:click="open = false"></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 flex w-full {{ $w }} flex-col bg-white shadow-2xl ring-1 ring-slate-900/5"
        role="dialog" aria-modal="true"
    >
        <div class="flex shrink-0 items-center justify-between gap-4 border-b border-gray-100 px-6 py-4">
            <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
            <button type="button" x-on:click="open = false" class="-me-1 rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600" aria-label="{{ __('Close') }}">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex shrink-0 items-center justify-end gap-2 border-t border-gray-100 bg-gray-50/60 px-6 py-4">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
