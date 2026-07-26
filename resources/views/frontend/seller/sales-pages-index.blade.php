<x-layouts.app :title="__('Sales pages')">
    @php
        $total = count($pages);
        $live = collect($pages)->where('published', true)->count();
        $drafts = $total - $live;
    @endphp

    <div class="mt-6 space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('shop') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900"><x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Shop') }}</a>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Sales pages') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Each page sells one product. Generate a page from a product, customize it, then publish and share.') }}</p>
            </div>
            @if ($hasProducts)
                <x-ui.button href="{{ route('shop.products') }}" icon="plus">{{ __('New page from a product') }}</x-ui.button>
            @else
                <x-ui.button href="{{ route('shop.products.create') }}" icon="plus">{{ __('Create a product') }}</x-ui.button>
            @endif
        </div>

        @if (session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif

        @if ($total)
            {{-- Stats --}}
            <div class="grid grid-cols-3 gap-3">
                @foreach ([[__('Total pages'), $total, 'document-text', 'text-neutral-900'], [__('Live'), $live, 'globe-alt', 'text-emerald-600'], [__('Drafts'), $drafts, 'pencil-square', 'text-amber-600']] as [$label, $value, $icon, $tint])
                    <div class="pp-card flex items-center gap-3 p-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-neutral-50 {{ $tint }}"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" /></span>
                        <div>
                            <p class="text-xl font-bold tracking-tight text-neutral-900">{{ $value }}</p>
                            <p class="text-xs text-neutral-500">{{ $label }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Nudge: unpublished drafts are not making money yet --}}
            @if ($drafts)
                <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-sm text-amber-800">
                    <x-heroicon-o-megaphone class="h-5 w-5 shrink-0" />
                    <span>{{ trans_choice('You have :count draft that isn’t live yet — publish it to start selling.|You have :count drafts that aren’t live yet — publish them to start selling.', $drafts, ['count' => $drafts]) }}</span>
                </div>
            @endif

            {{-- Pages --}}
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($pages as $pg)
                    @php $url = $pg['domain'] ? 'https://'.$pg['domain'] : route('funnel.sales', ['slug' => $pg['slug']]); @endphp
                    <div class="pp-card flex flex-col p-5 @if (! $pg['published']) ring-1 ring-amber-200 @endif">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-neutral-900">{{ $pg['name'] }}</p>
                                <p class="mt-0.5 flex items-center gap-1 truncate text-xs text-neutral-500">
                                    <x-heroicon-o-cube class="h-3.5 w-3.5 shrink-0" /> {{ $pg['product'] }}
                                </p>
                            </div>
                            <x-ui.badge :color="$pg['color']" dot>{{ $pg['published'] ? __('Live') : __('Draft') }}</x-ui.badge>
                        </div>

                        {{-- Share link (live) or publish prompt (draft) --}}
                        @if ($pg['published'])
                            <div x-data="{ copied: false }" class="mt-4 flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50/70 px-3 py-2">
                                <x-heroicon-o-link class="h-3.5 w-3.5 shrink-0 text-neutral-400" />
                                <span class="truncate font-mono text-[11px] text-neutral-600">{{ preg_replace('#^https?://#', '', $url) }}</span>
                                <button type="button"
                                    x-on:click="navigator.clipboard.writeText('{{ $url }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="ml-auto shrink-0 rounded-md px-2 py-1 text-[11px] font-semibold text-brand-600 transition hover:bg-brand-50"
                                    x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</button>
                            </div>
                        @else
                            <p class="mt-4 flex items-center gap-1.5 rounded-lg border border-dashed border-amber-200 bg-amber-50/50 px-3 py-2 text-[11px] font-medium text-amber-700">
                                <x-heroicon-o-eye-slash class="h-3.5 w-3.5 shrink-0" /> {{ __('Not live yet — finish and publish to share.') }}
                            </p>
                        @endif

                        <p class="mt-3 text-[11px] text-neutral-400">{{ __('Edited :time', ['time' => $pg['updated'] ?? '—']) }}</p>

                        {{-- Actions --}}
                        <div class="mt-4 flex items-center gap-2 border-t border-neutral-100 pt-4">
                            @if ($pg['published'])
                                <x-ui.button href="{{ route('shop.sales-page.edit', ['slug' => $pg['slug']]) }}" size="sm" variant="secondary" icon="paint-brush">{{ __('Edit') }}</x-ui.button>
                                <a href="{{ $url }}" target="_blank" class="rounded-lg border border-neutral-200 p-2 text-neutral-400 transition hover:bg-neutral-50 hover:text-neutral-600" title="{{ __('View') }}"><x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /></a>
                            @else
                                <x-ui.button href="{{ route('shop.sales-page.edit', ['slug' => $pg['slug']]) }}" size="sm" icon="rocket-launch">{{ __('Finish & publish') }}</x-ui.button>
                            @endif

                            <form method="POST" action="{{ route('shop.sales-page.duplicate', ['slug' => $pg['slug']]) }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-neutral-200 p-2 text-neutral-400 transition hover:bg-neutral-50 hover:text-neutral-600" title="{{ __('Duplicate') }}"><x-heroicon-o-document-duplicate class="h-4 w-4" /></button>
                            </form>
                            <button type="button" x-on:click="$dispatch('open-modal', 'delete-page-{{ $loop->index }}')" class="ml-auto rounded-lg border border-neutral-200 p-2 text-neutral-400 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" title="{{ __('Delete') }}"><x-heroicon-o-trash class="h-4 w-4" /></button>
                        </div>
                    </div>

                    <x-ui.modal name="delete-page-{{ $loop->index }}" :title="__('Delete sales page')" :subtitle="$pg['name']" maxWidth="sm">
                        <p class="text-sm leading-relaxed text-neutral-600">
                            {{ __('This removes the sales page and its public link stops working. The product it sells is not affected — you can generate a new page for it any time.') }}
                        </p>
                        <div class="mt-6 flex items-center justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                            <form method="POST" action="{{ route('shop.sales-page.delete', ['slug' => $pg['slug']]) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="danger" icon="trash">{{ __('Delete page') }}</x-ui.button>
                            </form>
                        </div>
                    </x-ui.modal>
                @endforeach
            </div>
        @else
            {{-- Empty state — funnel to where pages are generated --}}
            <div class="pp-card">
                @if ($hasProducts)
                    <x-ui.empty-state icon="document-text" :title="__('Generate your first sales page')" :description="__('Open a product, publish it, then hit “Generate sales page” — you’ll get a ready-to-edit page in seconds.')">
                        <x-slot:action>
                            <x-ui.button href="{{ route('shop.products') }}" icon="arrow-right">{{ __('Go to products') }}</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="cube" :title="__('Create a product first')" :description="__('A sales page always sells one product. Create a product, publish it, then generate its page.')">
                        <x-slot:action>
                            <x-ui.button href="{{ route('shop.products.create') }}" icon="plus">{{ __('Create product') }}</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @endif
            </div>
        @endif
    </div>
</x-layouts.app>
