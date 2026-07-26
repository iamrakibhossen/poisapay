<x-layouts.app :title="__('Media Library')">
    <div class="mt-6 space-y-6" x-data="mediaManager(@js($config))">
        {{-- ── Header ─────────────────────────────────────────────────────── --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <a href="{{ route('shop') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900"><x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Shop') }}</a>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Media Library') }}</h1>
                <p class="mt-1 max-w-xl text-sm text-neutral-500">{{ __('Upload once, reuse across every page and section. Images are optimised automatically — WebP + responsive sizes for fast, high-converting pages.') }}</p>
            </div>
            <div class="flex items-center gap-2.5">
                <div class="rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-center">
                    <p class="text-lg font-semibold leading-none text-neutral-900" x-text="media.total || {{ $stats['count'] }}"></p>
                    <p class="mt-1 text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ __('Images') }}</p>
                </div>
                <div class="rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-center">
                    <p class="text-lg font-semibold leading-none text-neutral-900">{{ $stats['size'] }}</p>
                    <p class="mt-1 text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ __('Storage') }}</p>
                </div>
            </div>
        </div>

        {{-- ── Hero drag & drop upload zone ───────────────────────────────── --}}
        <div @click="!media.trashed && mediaBrowse()"
            @dragover.prevent="!media.trashed && (media.dragOver = true)" @dragleave.prevent="media.dragOver = false" @drop.prevent="mediaDrop($event)"
            :class="media.dragOver ? 'border-brand-500 bg-brand-50' : 'border-neutral-300 bg-gradient-to-b from-neutral-50 to-white hover:border-brand-400 hover:bg-brand-50/40'"
            class="group relative cursor-pointer overflow-hidden rounded-2xl border-2 border-dashed p-8 text-center transition"
            x-show="!media.trashed" x-cloak>
            <div class="pointer-events-none flex flex-col items-center gap-3">
                <span class="grid h-14 w-14 place-items-center rounded-2xl bg-brand-500/10 text-brand-600 transition group-hover:scale-105">
                    <x-heroicon-o-arrow-up-tray class="h-6 w-6" />
                </span>
                <div>
                    <p class="text-sm font-semibold text-neutral-900">{{ __('Drag & drop images here, or click to browse') }}</p>
                    <p class="mt-1 text-xs text-neutral-500">{{ __('JPG · PNG · WebP · GIF · SVG — up to :mb MB each. Auto-optimised to WebP + thumbnail/medium/large.', ['mb' => round(($config['maxKb'] ?? 12288) / 1024)]) }}</p>
                </div>
            </div>

            {{-- Inline upload progress (overlays the zone) --}}
            <div x-show="media.uploading" x-cloak class="absolute inset-x-0 bottom-0 px-6 pb-4">
                <div class="flex items-center gap-2 text-xs font-medium text-brand-700">
                    <span>{{ __('Uploading…') }}</span>
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-brand-100">
                        <div class="h-full rounded-full bg-brand-500 transition-all" :style="`width:${media.uploadPct}%`"></div>
                    </div>
                    <span x-text="media.uploadPct + '%'"></span>
                </div>
            </div>
        </div>

        {{-- ── Library ────────────────────────────────────────────────────── --}}
        <div class="h-[62vh] overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
            @include('frontend.seller.partials.media-panel')
        </div>
    </div>
</x-layouts.app>
