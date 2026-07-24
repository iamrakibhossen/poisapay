@props(['paginator'])

{{-- Prev / "Page X of Y" / Next pager for a LengthAwarePaginator. Preserves the
     query string as long as the paginator was built with ->withQueryString(). --}}
@if ($paginator->hasPages())
    <div {{ $attributes->merge(['class' => 'flex items-center justify-between text-sm']) }}>
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-100 px-3 py-1.5 font-medium text-neutral-300">
                <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50">
                <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
            </a>
        @endif
        <span class="text-neutral-500">{{ __('Page :page of :last', ['page' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50">
                {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
            </a>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-100 px-3 py-1.5 font-medium text-neutral-300">
                {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
            </span>
        @endif
    </div>
@endif
