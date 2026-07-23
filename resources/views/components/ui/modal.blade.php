@props([
    'name',
    'title' => null,
    'subtitle' => null,
    'maxWidth' => 'md',   // sm | md | lg | xl | 2xl | full
])

@php
    $widths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-2xl',
        '2xl' => 'sm:max-w-3xl',
        'full' => 'sm:max-w-[calc(100vw-3rem)] sm:h-[calc(100vh-3rem)]',
    ];
    $width = $widths[$maxWidth] ?? $widths['md'];
@endphp

{{-- Mercury-style dialog: airy spacing, hairline slate ring, soft shadow, clean
     white footer. Open via $dispatch('open-modal', '<name>'). --}}
<div
    x-data="{ open: false }"
    x-on:open-modal.window="$event.detail === '{{ $name }}' && (open = true)"
    x-on:close-modal.window="$event.detail === '{{ $name }}' && (open = false)"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
    style="display: none;"
>
    {{-- Scrim --}}
    <div x-show="open" x-transition.opacity
        class="fixed inset-0 bg-slate-950/40" x-on:click="open = false"></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-[0.97]"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 sm:scale-100"
        x-transition:leave-end="opacity-0 sm:scale-[0.97]"
        class="relative flex max-h-[90vh] w-full {{ $width }} flex-col overflow-hidden rounded-2xl bg-white shadow-[0_24px_70px_-24px_rgba(15,23,42,0.35)] ring-1 ring-slate-200/80"
        role="dialog" aria-modal="true"
    >
        @if ($title)
            <div class="flex shrink-0 items-start justify-between gap-4 px-10 pt-10 pb-5">
                <div class="min-w-0">
                    <h3 class="text-[16px] font-semibold leading-6 tracking-[-0.01em] text-slate-900">{{ $title }}</h3>
                    @if ($subtitle)
                        <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
                    @endif
                </div>
                <button type="button" x-on:click="open = false" class="-me-2 -mt-1 rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 cursor-pointer" aria-label="{{ __('Close') }}">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>
        @else
            <button type="button" x-on:click="open = false" class="absolute end-5 top-5 z-10 rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 cursor-pointer" aria-label="{{ __('Close') }}">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        @endif

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto px-10 text-slate-600 {{ $title ? 'pt-1' : 'pt-7' }} {{ isset($footer) ? 'pb-5' : 'pb-7' }}">
            {{ $slot }}
        </div>

        {{-- Footer (bottom inset matches the 28px side padding) --}}
        @isset($footer)
            <div class="flex shrink-0 items-center justify-end gap-3 border-t border-slate-100 px-10 pt-5 pb-10">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
