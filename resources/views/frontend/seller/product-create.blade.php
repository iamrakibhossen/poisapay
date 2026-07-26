@php
    $editing = ! empty($product);
    $attrs = $editing ? ($product->attributes ?? []) : [];
    $priceValue = $editing && $product->priceAsset ? $product->priceAsset->money($product->price_amount)->toDecimal() : '';
    $typeValue = $editing ? $product->type->value : 'digital';
@endphp
<x-layouts.app :title="$editing ? __('Edit product') : __('Create product')">
    <div class="mx-auto mt-6 max-w-3xl space-y-6">
        <div>
            <a href="{{ route('shop.products') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900">
                <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Products') }}
            </a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-neutral-900">{{ $editing ? __('Edit product') : __('Create product') }}</h1>
            <p class="mt-1 text-sm text-neutral-500">
                {{ $editing ? __('Changes go live on the sales page immediately.') : __('Create it, then publish to generate a shareable sales page.') }}
            </p>
        </div>

        @if ($errors->has('product'))
            <x-ui.alert type="danger">{{ $errors->first('product') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert type="danger">{{ session('error') }}</x-ui.alert>
        @endif

        @if ($editing)
            @if (session('success'))
                <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
            @endif
            @if ($errors->has('sales_page'))
                <x-ui.alert type="danger">{{ $errors->first('sales_page') }}</x-ui.alert>
            @endif

            @php
                $isPublished = $product->status === \App\Shop\Enums\ProductStatus::Published;
                $page = $salesPage ?? null;
                $pageLive = $page && $page->status === \App\Shop\Enums\SalesPageStatus::Published;
            @endphp

            <x-ui.card :class="$isPublished && ! $page ? 'ring-1 ring-brand-500/30 bg-brand-50/40' : ''">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $isPublished ? 'bg-emerald-50 text-emerald-600' : 'bg-neutral-100 text-neutral-500' }}">
                            <x-dynamic-component :component="'heroicon-o-'.($isPublished ? 'check-badge' : 'clock')" class="h-5 w-5" />
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-semibold text-neutral-900">{{ __('Sales page') }}</h2>
                                <x-ui.badge :color="$isPublished ? 'success' : 'gray'" dot>{{ ucfirst($product->status->value) }}</x-ui.badge>
                            </div>
                            <p class="mt-1 text-sm text-neutral-500">
                                @if (! $isPublished)
                                    {{ __('Publish this product to make it sellable and generate a shareable sales page.') }}
                                @elseif (! $page)
                                    {{ __('Your product is live — generate its sales page to start taking orders.') }}
                                @elseif ($pageLive)
                                    {{ __('Your sales page is live and ready to share.') }}
                                @else
                                    {{ __('Draft sales page — finish it in the builder, then publish.') }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        @if (! $isPublished)
                            <form method="POST" action="{{ route('shop.products.publish', $product->id) }}">
                                @csrf
                                <x-ui.button type="submit" icon="rocket-launch">{{ __('Publish product') }}</x-ui.button>
                            </form>
                        @elseif (! $page)
                            <form method="POST" action="{{ route('shop.sales-pages.store') }}">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="name" value="{{ $product->name }}">
                                <x-ui.button type="submit" icon="sparkles">{{ __('Generate sales page') }}</x-ui.button>
                            </form>
                        @else
                            @if ($pageLive)
                                <x-ui.button href="{{ route('funnel.sales', ['slug' => $page->slug]) }}" target="_blank" variant="secondary" icon="arrow-top-right-on-square">{{ __('View page') }}</x-ui.button>
                            @endif
                            <x-ui.button href="{{ route('shop.sales-page.edit', ['slug' => $page->slug]) }}" icon="pencil-square">{{ __('Open builder') }}</x-ui.button>
                        @endif
                    </div>
                </div>
            </x-ui.card>
        @endif

        <form method="POST" action="{{ $editing ? route('shop.products.update', $product->id) : route('shop.products.store') }}" class="space-y-6"
            x-data="{ type: @js(old('type', $typeValue)), price: @js(old('price', $priceValue)) }">
            @csrf
            @if ($editing) @method('PUT') @endif

            {{-- Type --}}
            <x-ui.card>
                <h2 class="mb-1 text-sm font-semibold text-neutral-900">{{ __('Product type') }}</h2>
                <p class="mb-4 text-xs text-neutral-500">{{ __('This decides how the product is delivered.') }}</p>
                <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($types as $key => [$label, $icon, $desc])
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="{{ $key }}" x-model="type" class="peer sr-only" />
                            <span class="flex h-full flex-col gap-1.5 rounded-xl border p-3.5 transition
                                border-neutral-200 hover:border-neutral-300
                                peer-checked:border-brand-500 peer-checked:bg-brand-50/50 peer-checked:ring-1 peer-checked:ring-brand-500">
                                <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" /></span>
                                <span class="text-sm font-semibold text-neutral-900">{{ $label }}</span>
                                <span class="text-[11px] leading-snug text-neutral-500">{{ $desc }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('type')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
            </x-ui.card>

            {{-- Basics --}}
            <x-ui.card>
                <h2 class="mb-4 text-sm font-semibold text-neutral-900">{{ __('Details') }}</h2>
                <x-ui.input :label="__('Product name')" name="name" icon="cube" :value="old('name', $editing ? $product->name : '')"
                    :error="$errors->first('name')" placeholder="LaunchKit — Laravel SaaS Boilerplate" required />
                <div class="mt-4">
                    <x-ui.input :label="__('Short tagline')" name="summary" icon="sparkles" :value="old('summary', $editing ? $product->summary : '')"
                        :error="$errors->first('summary')" placeholder="Ship your SaaS in a weekend, not a quarter." />
                </div>
                <div class="mt-4">
                    <x-ui.textarea :label="__('Description')" name="description" rows="4"
                        :hint="__('This appears on your sales page.')" :error="$errors->first('description')">{{ old('description', $editing ? $product->description : '') }}</x-ui.textarea>
                </div>
            </x-ui.card>

            {{-- Pricing --}}
            <x-ui.card>
                <h2 class="mb-4 text-sm font-semibold text-neutral-900">{{ __('Pricing') }}</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-ui.input :label="__('Price')" name="price" type="number" step="0.01" prefix="$" x-model="price"
                            :error="$errors->first('price')" placeholder="49.00" required />
                    </div>
                    <x-ui.select :label="__('Currency')" name="price_asset_id">
                        @foreach ($assets as $a)
                            <option value="{{ $a['id'] }}" @selected((string) old('price_asset_id', $editing ? $product->price_asset_id : '') === (string) $a['id'])>{{ $a['symbol'] }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <p class="mt-2 text-xs text-neutral-400">{{ __('Set price to 0 to offer the product for free.') }}</p>
            </x-ui.card>

            {{-- Delivery (adapts to type) --}}
            <x-ui.card>
                <h2 class="mb-1 text-sm font-semibold text-neutral-900">{{ __('Delivery') }}</h2>
                <p class="mb-4 text-xs text-neutral-500">{{ __('What the buyer receives after payment.') }}</p>

                <div x-show="['digital','license'].includes(type)">
                    <div class="rounded-xl border border-dashed border-neutral-300 p-6 text-center">
                        <x-heroicon-o-arrow-up-tray class="mx-auto h-6 w-6 text-neutral-400" />
                        <p class="mt-2 text-sm font-medium text-neutral-700">{{ __('Upload your file') }}</p>
                        <p class="text-xs text-neutral-400">{{ __('ZIP, PDF, or any digital asset — stored privately, delivered via signed link.') }}</p>
                        <input type="file" name="file" class="mt-3 text-xs" />
                    </div>
                </div>
                <div x-show="type === 'physical'" x-cloak class="space-y-4"
                    x-data="{
                        seed: @js($variantSeed ?? ['has' => false, 'options' => [], 'rows' => []]),
                        hasVariations: false,
                        options: [],
                        matrix: [],
                        init() {
                            this.options = JSON.parse(JSON.stringify(this.seed.options || [])).map(o => ({ ...o, _new: '' }));
                            this.hasVariations = !! this.seed.has;
                            this.rebuild();
                        },
                        addOption() { this.options.push({ name: '', values: [], _new: '' }); this.rebuild(); },
                        removeOption(i) { this.options.splice(i, 1); this.rebuild(); },
                        addValue(o) { const v = o._new.trim(); if (v && ! o.values.includes(v)) o.values.push(v); o._new = ''; this.rebuild(); },
                        removeValue(o, j) { o.values.splice(j, 1); this.rebuild(); },
                        combos() {
                            let combos = [{}];
                            for (const o of this.options) {
                                const name = (o.name || '').trim() || 'Option';
                                if (! o.values.length) continue;
                                combos = combos.flatMap(c => o.values.map(v => ({ ...c, [name]: v })));
                            }
                            return combos;
                        },
                        keyOf(map) { return Object.values(map).join(' / '); },
                        rebuild() {
                            const prev = Object.fromEntries(this.matrix.map(r => [r.key, r]));
                            this.matrix = this.combos().map(map => {
                                const key = this.keyOf(map);
                                const src = prev[key] || (this.seed.rows || {})[key] || {};
                                return { key, options: map, id: src.id || '', price: src.price ?? '', stock: (src.stock ?? '') === null ? '' : (src.stock ?? ''), sku: src.sku ?? '' };
                            });
                        },
                    }">
                    <p class="text-sm text-neutral-500">{{ __('This item ships to the buyer. They enter a delivery address at checkout.') }}</p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input :label="__('Weight (g)')" name="weight" type="number" placeholder="250" :value="old('weight', $attrs['weight'] ?? '')" />
                        <x-ui.input :label="__('SKU')" name="sku" placeholder="TEE-BLK-M" :value="old('sku', $attrs['sku'] ?? '')" />
                        <x-ui.input :label="__('Shipping fee')" name="shipping_fee" prefix="$" type="number" step="0.01" placeholder="5.00" :value="old('shipping_fee', $attrs['shipping_fee'] ?? '')" />
                    </div>
                    {{-- Variations --}}
                    <div class="rounded-xl border border-neutral-200 p-4">
                        <label class="flex items-center justify-between gap-3">
                            <span>
                                <span class="block text-sm font-medium text-neutral-800">{{ __('This product has variations') }}</span>
                                <span class="block text-xs text-neutral-500">{{ __('e.g. size, color, storage — each with its own stock & price.') }}</span>
                            </span>
                            <span class="relative inline-flex shrink-0 cursor-pointer items-center">
                                <input type="checkbox" x-model="hasVariations" class="peer sr-only" />
                                <span class="h-5 w-9 rounded-full bg-neutral-200 transition peer-checked:bg-brand-500"></span>
                                <span class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-4"></span>
                            </span>
                        </label>

                        <div x-show="hasVariations" x-cloak class="mt-4 space-y-3">
                            {{-- Options --}}
                            <template x-for="(o, i) in options" :key="i">
                                <div class="rounded-lg border border-neutral-200 bg-neutral-50/60 p-3">
                                    <div class="flex items-center gap-2">
                                        <input x-model="o.name" x-on:input.debounce.250ms="rebuild()" placeholder="{{ __('Option name (e.g. Size)') }}"
                                            class="flex-1 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                        <button type="button" x-on:click="removeOption(i)" class="rounded-md p-1.5 text-neutral-400 hover:bg-rose-50 hover:text-rose-600"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <template x-for="(v, j) in o.values" :key="j">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">
                                                <span x-text="v"></span>
                                                <button type="button" x-on:click="removeValue(o, j)" class="text-brand-400 hover:text-brand-700">&times;</button>
                                            </span>
                                        </template>
                                        <input x-model="o._new" x-on:keydown.enter.prevent="addValue(o)" placeholder="{{ __('Add value + Enter') }}"
                                            class="w-32 rounded-lg border border-dashed border-neutral-300 bg-white px-2.5 py-1 text-xs focus:border-brand-500 focus:ring-0" />
                                    </div>
                                </div>
                            </template>

                            <button type="button" x-on:click="addOption()" class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 hover:text-brand-700">
                                <x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add option') }}
                            </button>

                            {{-- Generated variant matrix — one row per option combination.
                                 Only rendered (so only submitted) when variations are on and
                                 the product is physical; each row posts as variants[i][…]. --}}
                            <div class="overflow-hidden rounded-lg border border-neutral-200">
                                <div class="flex items-center justify-between bg-neutral-50 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                                    <span>{{ __('Variant') }} · {{ __('price') }} · {{ __('stock') }}</span>
                                    <span><span x-text="matrix.length"></span> {{ __('variants') }}</span>
                                </div>
                                <div class="divide-y divide-neutral-100">
                                    <template x-if="type === 'physical' && hasVariations">
                                        <div>
                                            <template x-for="(row, i) in matrix" :key="row.key">
                                                <div class="flex items-center gap-2 px-3 py-2">
                                                    <span class="flex-1 text-sm font-medium text-neutral-800" x-text="row.key"></span>
                                                    <template x-if="row.id"><input type="hidden" :name="`variants[${i}][id]`" :value="row.id" /></template>
                                                    <template x-for="(val, name) in row.options" :key="name">
                                                        <input type="hidden" :name="`variants[${i}][options][${name}]`" :value="val" />
                                                    </template>
                                                    <div class="flex items-center rounded-md border border-neutral-200 bg-white px-2 text-xs text-neutral-400">
                                                        <span>$</span>
                                                        <input type="text" :name="`variants[${i}][price]`" x-model="row.price" placeholder="{{ __('inherit') }}" class="w-16 border-0 py-1 text-sm focus:ring-0" />
                                                    </div>
                                                    <input type="number" :name="`variants[${i}][stock]`" x-model="row.stock" placeholder="{{ __('Stock') }}" class="w-20 rounded-md border border-neutral-200 px-2 py-1 text-sm focus:border-brand-500 focus:ring-0" />
                                                    <input type="hidden" :name="`variants[${i}][sku]`" :value="row.sku" />
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="! matrix.length">
                                        <p class="px-3 py-3 text-center text-xs text-neutral-400">{{ __('Add option values to generate variants.') }}</p>
                                    </template>
                                </div>
                            </div>
                            @error('variants')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        {{-- Simple stock when no variations (only submitted when variations off) --}}
                        <template x-if="! hasVariations">
                            <div class="mt-4">
                                <x-ui.input :label="__('Stock')" name="stock" type="number" placeholder="100" class="max-w-[200px]" :value="old('stock', $attrs['stock'] ?? '')" />
                            </div>
                        </template>
                    </div>
                </div>
                <div x-show="type === 'service'" x-cloak class="text-sm text-neutral-500">
                    {{ __('Buyers submit a brief at checkout; you deliver the outcome and mark the order complete.') }}
                </div>
                <div x-show="['membership','subscription'].includes(type)" x-cloak class="text-sm text-neutral-500">
                    {{ __('Set the billing cadence and gated content after creating the product.') }}
                </div>
            </x-ui.card>

            <div class="flex items-center justify-end gap-3">
                <x-ui.button href="{{ route('shop.products') }}" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="check">{{ $editing ? __('Save changes') : __('Create product') }}</x-ui.button>
            </div>
        </form>

        @if ($editing)
            <div class="mt-8 rounded-2xl border border-red-100 bg-red-50/40 p-5" x-data>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">{{ __('Delete product') }}</h3>
                        <p class="mt-0.5 text-sm text-neutral-500">
                            @if ($canDelete ?? false)
                                {{ __('Permanently remove this product. This can’t be undone.') }}
                            @else
                                {{ __('This product has orders and can’t be deleted — archive it instead.') }}
                            @endif
                        </p>
                    </div>
                    @if ($canDelete ?? false)
                        <x-ui.button type="button" variant="danger" icon="trash" x-on:click="$dispatch('open-modal', 'shop-product-delete')">{{ __('Delete') }}</x-ui.button>
                    @else
                        <x-ui.button type="button" variant="secondary" icon="lock-closed" disabled>{{ __('Delete') }}</x-ui.button>
                    @endif
                </div>
            </div>

            @if ($canDelete ?? false)
                <x-ui.modal name="shop-product-delete" :title="__('Delete product')" maxWidth="sm">
                    <p class="text-sm text-neutral-600">{{ __('Delete') }} <span class="font-medium text-neutral-900">{{ $product->name }}</span>? {{ __('This can’t be undone.') }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'shop-product-delete')">{{ __('Cancel') }}</x-ui.button>
                        <form method="POST" action="{{ route('shop.products.delete', $product->id) }}">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" variant="danger" icon="trash">{{ __('Delete product') }}</x-ui.button>
                        </form>
                    </div>
                </x-ui.modal>
            @endif
        @endif
    </div>
</x-layouts.app>
