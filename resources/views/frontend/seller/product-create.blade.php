@php
    $editing = ! empty($product);
    $attrs = $editing ? ($product->attributes ?? []) : [];
    $priceValue = $editing && $product->priceAsset ? $product->priceAsset->money($product->price_amount)->toDecimal() : '';
    $typeValue = $editing ? $product->type->value : 'digital';
@endphp
<x-layouts.app :title="$editing ? __('Edit product') : __('Create product')">
    <div class="mx-auto mt-6 max-w-3xl space-y-6">
        <div>
            <a href="{{ route('sell.products') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900">
                <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Products') }}
            </a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-neutral-900">{{ $editing ? __('Edit product') : __('Create product') }}</h1>
            <p class="mt-1 text-sm text-neutral-500">
                {{ $editing ? __('Changes go live on the sales page immediately.') : __('A shareable sales page is generated automatically when you publish.') }}
            </p>
        </div>

        @if ($errors->has('product'))
            <x-ui.alert type="danger">{{ $errors->first('product') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ $editing ? route('sell.products.update', $product->id) : route('sell.products.store') }}" class="space-y-6"
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
                        hasVariations: true,
                        options: [{ name: 'Size', values: ['S', 'M', 'L'], _new: '' }, { name: 'Color', values: ['Black', 'White'], _new: '' }],
                        addOption() { this.options.push({ name: '', values: [], _new: '' }); },
                        removeOption(i) { this.options.splice(i, 1); },
                        addValue(o) { const v = o._new.trim(); if (v && ! o.values.includes(v)) o.values.push(v); o._new = ''; },
                        removeValue(o, j) { o.values.splice(j, 1); },
                        get variants() {
                            let combos = [[]];
                            for (const o of this.options) {
                                if (! o.values.length) continue;
                                combos = combos.flatMap(c => o.values.map(v => [...c, v]));
                            }
                            return combos.map(c => c.join(' / '));
                        },
                    }">
                    <p class="text-sm text-neutral-500">{{ __('This item ships to the buyer. They enter a delivery address at checkout.') }}</p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input :label="__('Weight (g)')" name="weight" type="number" placeholder="250" :value="old('weight', $attrs['weight'] ?? '')" />
                        <x-ui.input :label="__('SKU')" name="sku" placeholder="TEE-BLK-M" :value="old('sku', $attrs['sku'] ?? '')" />
                        <x-ui.input :label="__('Shipping fee')" name="shipping_fee" prefix="$" type="number" step="0.01" placeholder="5.00" :value="old('shipping_fee', $attrs['shipping_fee'] ?? '')" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-neutral-600">
                        <input type="checkbox" name="cod" value="1" @checked(old('cod', $attrs['cod'] ?? false)) class="h-4 w-4 rounded border-neutral-300 text-brand-600" />
                        {{ __('Allow cash on delivery') }}
                    </label>

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
                                        <input x-model="o.name" placeholder="{{ __('Option name (e.g. Size)') }}"
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

                            {{-- Generated variant matrix --}}
                            <div class="overflow-hidden rounded-lg border border-neutral-200">
                                <div class="flex items-center justify-between bg-neutral-50 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                                    <span>{{ __('Variant') }}</span>
                                    <span><span x-text="variants.length"></span> {{ __('variants') }}</span>
                                </div>
                                <div class="divide-y divide-neutral-100">
                                    <template x-for="v in variants" :key="v">
                                        <div class="flex items-center gap-2 px-3 py-2">
                                            <span class="flex-1 text-sm font-medium text-neutral-800" x-text="v"></span>
                                            <div class="flex items-center rounded-md border border-neutral-200 bg-white px-2 text-xs text-neutral-400"><span>$</span><input type="text" placeholder="25.00" class="w-16 border-0 py-1 text-sm focus:ring-0" /></div>
                                            <input type="number" placeholder="{{ __('Stock') }}" class="w-20 rounded-md border border-neutral-200 px-2 py-1 text-sm focus:border-brand-500 focus:ring-0" />
                                        </div>
                                    </template>
                                    <template x-if="! variants.length">
                                        <p class="px-3 py-3 text-center text-xs text-neutral-400">{{ __('Add option values to generate variants.') }}</p>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Simple stock when no variations --}}
                        <div x-show="! hasVariations" x-cloak class="mt-4">
                            <x-ui.input :label="__('Stock')" name="stock" type="number" placeholder="100" class="max-w-[200px]" :value="old('stock', $attrs['stock'] ?? '')" />
                        </div>
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
                <x-ui.button href="{{ route('sell.products') }}" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="check">{{ $editing ? __('Save changes') : __('Create product') }}</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.app>
