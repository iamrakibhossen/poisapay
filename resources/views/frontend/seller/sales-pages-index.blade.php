<x-layouts.app :title="__('Sales pages')">
    <div class="mt-6 space-y-5" x-data="{ creating: false, product: '', name: '' }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Sales pages') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Create a page for a product, then customize it. A product can have several pages.') }}</p>
            </div>
            <x-ui.button x-on:click="creating = ! creating" icon="plus">{{ __('New sales page') }}</x-ui.button>
        </div>

        {{-- Create: pick a product first, then go customize --}}
        <div x-show="creating" x-cloak>
            <x-ui.card>
                <p class="mb-1 text-sm font-semibold text-neutral-900">{{ __('New sales page') }}</p>
                <p class="mb-4 text-xs text-neutral-500">{{ __('Every sales page sells one product. Pick the product, name the page, then customize it.') }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Product') }}</label>
                        <select x-model="product" class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                            <option value="">{{ __('Choose a product…') }}</option>
                            @foreach ($products as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                        <a href="{{ route('seller.products.create') }}" class="mt-1.5 inline-block text-[11px] font-medium text-brand-600 hover:text-brand-700">{{ __('+ Create a new product') }}</a>
                    </div>
                    <x-ui.input :label="__('Page name')" name="name" x-model="name" placeholder="e.g. Black Friday campaign" />
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <a x-bind:href="product ? '{{ route('seller.sales-page.edit', ['slug' => 'launchkit']) }}' : '#'"
                        x-bind:class="! product && 'pointer-events-none opacity-50'"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600">
                        <x-heroicon-o-paint-brush class="h-4 w-4" /> {{ __('Create & customize') }}
                    </a>
                    <p class="text-xs text-neutral-400" x-show="! product">{{ __('Choose a product to continue.') }}</p>
                </div>
            </x-ui.card>
        </div>

        {{-- Existing pages --}}
        @if (count($pages))
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($pages as $pg)
                    <div class="pp-card flex flex-col p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-neutral-900">{{ $pg['name'] }}</p>
                                <p class="truncate text-xs text-neutral-500">{{ $pg['product'] }}</p>
                            </div>
                            <x-ui.badge :color="$pg['color']" dot>{{ $pg['status'] }}</x-ui.badge>
                        </div>

                        <p class="mt-2 flex items-center gap-1 truncate font-mono text-[11px] text-neutral-400">
                            <x-heroicon-o-link class="h-3 w-3 shrink-0" />
                            {{ $pg['domain'] ? $pg['domain'] : 'poisahub.com/p/'.$pg['slug'] }}
                        </p>

                        <div class="mt-3 flex items-center gap-4 border-t border-neutral-100 pt-3 text-xs text-neutral-500">
                            <span>{{ __('Views') }} <span class="font-semibold text-neutral-700">{{ $pg['views'] }}</span></span>
                            <span>{{ __('Conv.') }} <span class="font-semibold text-neutral-700">{{ $pg['conv'] }}</span></span>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <x-ui.button href="{{ route('seller.sales-page.edit', ['slug' => $pg['slug']]) }}" size="sm" icon="paint-brush">{{ __('Customize') }}</x-ui.button>
                            <a href="{{ route('funnel.sales', ['slug' => $pg['slug']]) }}" target="_blank" class="rounded-lg border border-neutral-200 p-2 text-neutral-400 transition hover:bg-neutral-50 hover:text-neutral-600" title="{{ __('View') }}"><x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /></a>
                            <button type="button" class="rounded-lg border border-neutral-200 p-2 text-neutral-400 transition hover:bg-neutral-50 hover:text-neutral-600" title="{{ __('Duplicate') }}"><x-heroicon-o-document-duplicate class="h-4 w-4" /></button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="document-text" :title="__('No sales pages yet')" :description="__('Create a product, then build its sales page.')">
                    <x-slot:action>
                        <x-ui.button x-on:click="creating = true" icon="plus" size="sm">{{ __('New sales page') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @endif
    </div>
</x-layouts.app>
