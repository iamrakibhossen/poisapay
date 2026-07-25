<x-layouts.app :title="__('Sales pages')">
    <div class="mt-6 space-y-5" x-data="{ creating: {{ $errors->any() ? 'true' : 'false' }} }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Sales pages') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Create a page for a product, then customize it. A product can have several pages.') }}</p>
            </div>
            @if (count($products))
                <x-ui.button x-on:click="creating = ! creating" icon="plus">{{ __('New sales page') }}</x-ui.button>
            @endif
        </div>

        @if (session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif

        {{-- Create: pick a product first, then go customize --}}
        @if (count($products))
            <div x-show="creating" x-cloak>
                <form method="POST" action="{{ route('sell.sales-pages.store') }}">
                    @csrf
                    <x-ui.card>
                        <p class="mb-1 text-sm font-semibold text-neutral-900">{{ __('New sales page') }}</p>
                        <p class="mb-4 text-xs text-neutral-500">{{ __('Every sales page sells one product. Pick the product, name the page, then customize it.') }}</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Product') }}</label>
                                <select name="product_id" required class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                                    <option value="">{{ __('Choose a product…') }}</option>
                                    @foreach ($products as $id => $pname)
                                        <option value="{{ $id }}" @selected(old('product_id') === (string) $id)>{{ $pname }}</option>
                                    @endforeach
                                </select>
                                @error('product_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                <a href="{{ route('sell.products.create') }}" class="mt-1.5 inline-block text-[11px] font-medium text-brand-600 hover:text-brand-700">{{ __('+ Create a new product') }}</a>
                            </div>
                            <x-ui.input :label="__('Page name')" name="name" :value="old('name')" :error="$errors->first('name')" placeholder="e.g. Black Friday campaign" required />
                        </div>
                        @error('sales_page')<p class="mt-3 text-xs text-rose-600">{{ $message }}</p>@enderror
                        <div class="mt-4">
                            <x-ui.button type="submit" icon="paint-brush">{{ __('Create & customize') }}</x-ui.button>
                        </div>
                    </x-ui.card>
                </form>
            </div>
        @endif

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
                            <x-ui.button href="{{ route('sell.sales-page.edit', ['slug' => $pg['slug']]) }}" size="sm" icon="paint-brush">{{ __('Customize') }}</x-ui.button>
                            <a href="{{ route('funnel.sales', ['slug' => $pg['slug']]) }}" target="_blank" class="rounded-lg border border-neutral-200 p-2 text-neutral-400 transition hover:bg-neutral-50 hover:text-neutral-600" title="{{ __('View') }}"><x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /></a>
                            <button type="button" class="rounded-lg border border-neutral-200 p-2 text-neutral-400 transition hover:bg-neutral-50 hover:text-neutral-600" title="{{ __('Duplicate') }}"><x-heroicon-o-document-duplicate class="h-4 w-4" /></button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="pp-card">
                @if (count($products))
                    <x-ui.empty-state icon="document-text" :title="__('No sales pages yet')" :description="__('Pick a product and build its sales page.')">
                        <x-slot:action>
                            <x-ui.button x-on:click="creating = true" icon="plus" size="sm">{{ __('New sales page') }}</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="document-text" :title="__('Create a product first')" :description="__('A sales page always sells one product. Create a product, then build its page.')">
                        <x-slot:action>
                            <x-ui.button href="{{ route('sell.products.create') }}" icon="plus" size="sm">{{ __('Create product') }}</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @endif
            </div>
        @endif
    </div>
</x-layouts.app>
