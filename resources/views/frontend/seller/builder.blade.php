@php
    $config = [
        'document' => $document,
        'schemas' => $schemas,
        'palette' => $palette,
        'templates' => $templates,
        'endpoints' => $endpoints,
        'name' => $name,
        'productId' => (string) $productId,
        'products' => $products,
        'csrf' => csrf_token(),
        'savedAt' => optional($page->updated_at)->toIso8601String(),
        'seoOgImage' => $seo['og_image'],
        // Media Library picker config (shared endpoints + upload constraints).
        'media' => [
            'endpoints' => [
                'items' => route('shop.media.items'),
                'upload' => route('shop.media.store'),
            ],
            'accept' => (array) config('media.accept', []),
            'maxKb' => (int) config('media.max_upload_kb', 12288),
            'csrf' => csrf_token(),
        ],
    ];
    $railBtn = 'flex-1 rounded-md px-2 py-1.5 text-xs font-semibold transition';
@endphp

<x-layouts.builder :title="$name">
    {{-- Editor affordances for the inline live canvas (hover + selected outlines). --}}
    <style>
        .pp-canvas [id^="b_"]{position:relative}
        .pp-canvas [data-pp-hover]{outline:2px dashed color-mix(in srgb, var(--pp-accent,#2563eb) 55%, transparent);outline-offset:-2px;cursor:pointer}
        .pp-canvas [data-pp-selected]{outline:2px solid var(--pp-accent,#2563eb);outline-offset:-2px}
        .pp-canvas [data-edit],.pp-canvas [data-edit-rich]{cursor:text}
        .pp-canvas .pp-editing{outline:2px solid var(--pp-accent,#2563eb);outline-offset:2px;border-radius:3px;background:color-mix(in srgb,var(--pp-accent,#2563eb) 6%,transparent)}
    </style>

    <div x-data="pageBuilder(@js($config))" x-cloak class="flex h-full flex-col">

        {{-- ============ Top toolbar ============ --}}
        <header class="flex items-center justify-between gap-3 border-b border-neutral-200 bg-white px-4 py-2.5">
            <div class="flex min-w-0 items-center gap-3">
                <a href="{{ route('shop.sales-pages') }}" class="grid h-8 w-8 place-items-center rounded-lg text-neutral-500 transition hover:bg-neutral-100" title="{{ __('Back') }}">
                    <x-heroicon-o-chevron-left class="h-4 w-4" />
                </a>
                <input x-model="name" @input="dirty = true; scheduleSave()" maxlength="120"
                    class="w-56 truncate rounded-md border border-transparent bg-transparent px-2 py-1 text-sm font-semibold text-neutral-900 transition hover:border-neutral-200 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-brand-500" />
                <span class="hidden items-center gap-1.5 text-xs text-neutral-400 sm:flex">
                    <template x-if="saving"><span class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-400"></span> {{ __('Saving…') }}</span></template>
                    <template x-if="!saving && !dirty && savedAt"><span class="flex items-center gap-1.5"><x-heroicon-o-check class="h-3.5 w-3.5 text-emerald-500" /> {{ __('Saved') }}</span></template>
                    <template x-if="!saving && dirty"><span>{{ __('Unsaved changes') }}</span></template>
                </span>
            </div>

            {{-- device switch --}}
            <div class="flex items-center gap-1 rounded-lg border border-neutral-200 p-0.5">
                @foreach (['desktop' => 'computer-desktop', 'tablet' => 'device-tablet', 'mobile' => 'device-phone-mobile'] as $d => $icon)
                    <button type="button" @click="setDevice('{{ $d }}')" :class="device === '{{ $d }}' ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:text-neutral-900'" class="grid h-7 w-8 place-items-center rounded-md transition">
                        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" />
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-1.5">
                <button type="button" @click="undo()" :disabled="!past.length" class="grid h-8 w-8 place-items-center rounded-lg text-neutral-500 transition hover:bg-neutral-100 disabled:opacity-30" title="{{ __('Undo') }}"><x-heroicon-o-arrow-uturn-left class="h-4 w-4" /></button>
                <button type="button" @click="redo()" :disabled="!future.length" class="grid h-8 w-8 place-items-center rounded-lg text-neutral-500 transition hover:bg-neutral-100 disabled:opacity-30" title="{{ __('Redo') }}"><x-heroicon-o-arrow-uturn-right class="h-4 w-4" /></button>
                <button type="button" @click="showShortcuts = true" class="grid h-8 w-8 place-items-center rounded-lg text-neutral-500 transition hover:bg-neutral-100" title="{{ __('Keyboard shortcuts (?)') }}"><x-heroicon-o-command-line class="h-4 w-4" /></button>
                <button type="button" @click="openMediaManager()" class="ml-1 hidden items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-neutral-50 sm:inline-flex"><x-heroicon-o-photo class="h-4 w-4" /> {{ __('Media') }}</button>
                <button type="button" @click="showTemplates = true" class="ml-1 hidden items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-neutral-50 sm:inline-flex"><x-heroicon-o-squares-2x2 class="h-4 w-4" /> {{ __('Templates') }}</button>
                <a href="{{ $publicUrl }}" target="_blank" class="ml-1 hidden rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-neutral-50 sm:inline-flex">{{ __('View live') }}</a>
                <form x-ref="publishForm" method="POST" action="{{ $endpoints['publish'] }}">
                    @csrf
                    <button type="button" @click="save().then(() => $refs.publishForm.submit())"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-3.5 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-600">
                        <x-heroicon-o-rocket-launch class="h-4 w-4" />
                        {{ $published ? __('Update live') : __('Publish') }}
                    </button>
                </form>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            {{-- ============ Left rail ============ --}}
            <aside class="flex w-72 flex-col border-r border-neutral-200 bg-white">
                <div class="flex gap-1 border-b border-neutral-100 p-2">
                    <button type="button" @click="leftTab = 'blocks'" :class="leftTab === 'blocks' ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:bg-neutral-100'" class="{{ $railBtn }}">{{ __('Blocks') }}</button>
                    <button type="button" @click="leftTab = 'layers'" :class="leftTab === 'layers' ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:bg-neutral-100'" class="{{ $railBtn }}">{{ __('Layers') }}</button>
                    <button type="button" @click="leftTab = 'theme'" :class="leftTab === 'theme' ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:bg-neutral-100'" class="{{ $railBtn }}">{{ __('Theme') }}</button>
                    <button type="button" @click="leftTab = 'settings'" :class="leftTab === 'settings' ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:bg-neutral-100'" class="{{ $railBtn }}">{{ __('Settings') }}</button>
                </div>

                <div class="flex-1 overflow-y-auto p-3">
                    {{-- ---- Blocks palette (static, from the registry) ---- --}}
                    <div x-show="leftTab === 'blocks'" class="space-y-5">
                        <p class="text-xs text-neutral-400">{{ __('Click to add to the page.') }}</p>
                        @foreach ($palette as $group)
                            <div>
                                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ $group['label'] }}</p>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ($group['blocks'] as $b)
                                        <button type="button" @click="addBlock('{{ $b['type'] }}')"
                                            class="group flex flex-col items-start gap-1.5 rounded-lg border border-neutral-200 p-2.5 text-left transition hover:border-brand-400 hover:bg-brand-50/40">
                                            <x-dynamic-component :component="'heroicon-o-'.$b['icon']" class="h-4 w-4 text-neutral-400 group-hover:text-brand-500" />
                                            <span class="text-xs font-medium text-neutral-700">{{ $b['label'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- ---- Layers tree (client, sortable) ---- --}}
                    <div x-show="leftTab === 'layers'">
                        <ul data-sortable data-parent="root" class="space-y-1">
                            <template x-for="node in doc.root.children" :key="node.id">
                                <li :data-id="node.id">
                                    <div @click="onLayerClick(node.id, $event)" :class="isSelected(node.id) ? 'border-brand-400 bg-brand-50' : 'border-transparent hover:bg-neutral-50'"
                                        class="flex items-center gap-2 rounded-md border px-2 py-1.5 text-xs">
                                        <span data-drag class="cursor-grab text-neutral-300 hover:text-neutral-500"><x-heroicon-o-bars-2 class="h-3.5 w-3.5" /></span>
                                        <span class="flex-1 truncate font-medium text-neutral-700" x-text="(schemas[node.type] && schemas[node.type].label) || node.type"></span>
                                        <button type="button" @click.stop="selectOne(node.id); removeSelected()" class="text-neutral-300 hover:text-rose-500"><x-heroicon-o-x-mark class="h-3.5 w-3.5" /></button>
                                    </div>
                                    {{-- one level of nesting for containers --}}
                                    <template x-if="node.children && node.children.length">
                                        <ul :data-parent="node.id" data-sortable class="ml-4 mt-1 space-y-1 border-l border-neutral-100 pl-2">
                                            <template x-for="child in node.children" :key="child.id">
                                                <li :data-id="child.id" @click.stop="onLayerClick(child.id, $event)" :class="isSelected(child.id) ? 'border-brand-400 bg-brand-50' : 'border-transparent hover:bg-neutral-50'"
                                                    class="flex items-center gap-2 rounded-md border px-2 py-1 text-xs">
                                                    <span data-drag class="cursor-grab text-neutral-300"><x-heroicon-o-bars-2 class="h-3 w-3" /></span>
                                                    <span class="flex-1 truncate text-neutral-600" x-text="(schemas[child.type] && schemas[child.type].label) || child.type"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </template>
                                </li>
                            </template>
                        </ul>
                        <p x-show="!doc.root.children.length" class="mt-4 text-center text-xs text-neutral-400">{{ __('No blocks yet — add one from the Blocks tab.') }}</p>
                    </div>

                    {{-- ---- Theme (global design tokens) ---- --}}
                    <div x-show="leftTab === 'theme'" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Brand colour') }}</label>
                            <input type="color" x-model="doc.globals.colors.brand" @input="touched()" class="h-9 w-full cursor-pointer rounded-lg border border-neutral-200" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Font') }}</label>
                            <select x-model="doc.globals.typography.font" @change="touched()" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                @foreach (['Inter', 'Poppins', 'Nunito', 'Lato', 'Georgia'] as $f)
                                    <option value="{{ $f }}">{{ $f }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Button shape') }}</label>
                            <select x-model="doc.globals.buttons.radius" @change="touched()" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                <option value="rounded">{{ __('Rounded') }}</option>
                                <option value="pill">{{ __('Pill') }}</option>
                                <option value="square">{{ __('Square') }}</option>
                            </select>
                        </div>
                        <div class="border-t border-neutral-100 pt-4">
                            <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Product this page sells') }}</label>
                            <select x-model="productId" @change="dirty = true; save()" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                @foreach ($products as $id => $pname)
                                    <option value="{{ $id }}">{{ $pname }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Direct checkout link — for buttons ("Open link" action) or external sites/ads. --}}
                        <div class="border-t border-neutral-100 pt-4" x-data="{ copied: false, url() { return '{{ rtrim(config('app.url'), '/') }}/checkout/' + productId } }">
                            <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Checkout link') }}</label>
                            <div class="flex gap-1.5">
                                <input type="text" readonly :value="url()" @focus="$event.target.select()" class="w-full truncate rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-xs text-neutral-600" />
                                <button type="button" @click="navigator.clipboard && navigator.clipboard.writeText(url()); copied = true; setTimeout(() => copied = false, 1500)" class="shrink-0 rounded-lg border border-neutral-200 px-2.5 text-xs font-semibold text-neutral-600 transition hover:bg-neutral-50" x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'"></button>
                            </div>
                            <p class="mt-1 text-[11px] text-neutral-400">{{ __('Sends buyers straight to this product’s checkout. Use it on any button (Open link) or an external site/ad. Publish the page first.') }}</p>
                        </div>
                    </div>

                    {{-- ---- Settings: SEO + revenue offers (server-authoritative; plain POST) ---- --}}
                    <div x-show="leftTab === 'settings'">
                        @php
                            $fldCls = 'w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500';
                            $lblCls = 'mb-1 block text-xs font-medium text-neutral-500';
                        @endphp
                        <form method="POST" action="{{ $endpoints['settings'] }}" class="space-y-5" @submit="save()">
                            @csrf
                            <input type="hidden" name="name" :value="name" />
                            <input type="hidden" name="product_id" :value="productId" />

                            <div class="space-y-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ __('SEO & sharing') }}</p>
                                <div><label class="{{ $lblCls }}">{{ __('Meta title') }}</label><input name="seo_title" maxlength="70" value="{{ $seo['title'] }}" class="{{ $fldCls }}" /></div>
                                <div><label class="{{ $lblCls }}">{{ __('Meta description') }}</label><textarea name="seo_description" rows="2" maxlength="200" class="{{ $fldCls }}">{{ $seo['description'] }}</textarea></div>
                                <div>
                                    <label class="{{ $lblCls }}">{{ __('Social share image') }}</label>
                                    <input type="hidden" name="seo_og_image" :value="seoOgImage" />
                                    <template x-if="seoOgImage">
                                        <div class="flex items-center gap-2">
                                            <img :src="seoOgImage" alt="" class="h-11 w-20 shrink-0 rounded-lg border border-neutral-200 object-cover" />
                                            <button type="button" @click="pickSeoImage()" class="flex-1 rounded-lg border border-neutral-200 px-2 py-2 text-xs font-medium text-neutral-600 transition hover:bg-neutral-50">{{ __('Change image') }}</button>
                                            <button type="button" @click="seoOgImage = ''" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-neutral-300 hover:bg-rose-50 hover:text-rose-500" title="{{ __('Remove') }}"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                        </div>
                                    </template>
                                    <template x-if="!seoOgImage">
                                        <button type="button" @click="pickSeoImage()" class="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-neutral-300 py-2.5 text-xs font-medium text-neutral-500 transition hover:border-brand-400 hover:text-brand-600"><x-heroicon-o-photo class="h-4 w-4" /> {{ __('Choose image') }}</button>
                                    </template>
                                    <p class="mt-1 text-[11px] text-neutral-400">{{ __('Shown when the page is shared on social media. 1200×630 recommended.') }}</p>
                                </div>
                                <label class="flex items-center gap-2 text-sm text-neutral-700"><input type="checkbox" name="seo_noindex" value="1" @checked($seo['noindex']) class="h-4 w-4 rounded border-neutral-300 text-brand-500" /> {{ __('Hide from search engines') }}</label>
                            </div>

                            @if ($offerProducts === [])
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-700">
                                    {{ __('Add another :cur product to offer it as a bump or upsell — offers must match this page’s currency.', ['cur' => $offers['currency']]) }}
                                </div>
                            @endif

                            <div class="space-y-3 border-t border-neutral-100 pt-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ __('Order bump') }} <span class="normal-case text-neutral-400">· {{ __('at checkout') }}</span></p>
                                <select name="bump_product_id" class="{{ $fldCls }}">
                                    <option value="">{{ __('No order bump') }}</option>
                                    @foreach ($offerProducts as $id => $pname)
                                        <option value="{{ $id }}" @selected($offers['bump_product_id'] === (string) $id)>{{ $pname }}</option>
                                    @endforeach
                                </select>
                                <div class="grid grid-cols-2 gap-2">
                                    <div><label class="{{ $lblCls }}">{{ __('Price') }} {{ $offers['currency'] }}</label><input name="bump_price" value="{{ $offers['bump_price'] }}" inputmode="decimal" class="{{ $fldCls }}" /></div>
                                    <div><label class="{{ $lblCls }}">{{ __('Headline') }}</label><input name="bump_headline" value="{{ $offers['bump_headline'] }}" class="{{ $fldCls }}" /></div>
                                </div>
                                <div><label class="{{ $lblCls }}">{{ __('Description') }}</label><textarea name="bump_description" rows="2" maxlength="400" class="{{ $fldCls }}">{{ $offers['bump_description'] }}</textarea></div>
                            </div>

                            <div class="space-y-3 border-t border-neutral-100 pt-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ __('1-click upsell') }} <span class="normal-case text-neutral-400">· {{ __('after purchase') }}</span></p>
                                <select name="upsell_product_id" class="{{ $fldCls }}">
                                    <option value="">{{ __('No upsell') }}</option>
                                    @foreach ($offerProducts as $id => $pname)
                                        <option value="{{ $id }}" @selected($offers['upsell_product_id'] === (string) $id)>{{ $pname }}</option>
                                    @endforeach
                                </select>
                                <div class="grid grid-cols-2 gap-2">
                                    <div><label class="{{ $lblCls }}">{{ __('Price') }} {{ $offers['currency'] }}</label><input name="upsell_price" value="{{ $offers['upsell_price'] }}" inputmode="decimal" class="{{ $fldCls }}" /></div>
                                    <div><label class="{{ $lblCls }}">{{ __('Headline') }}</label><input name="upsell_headline" value="{{ $offers['upsell_headline'] }}" class="{{ $fldCls }}" /></div>
                                </div>
                                <div><label class="{{ $lblCls }}">{{ __('Description') }}</label><textarea name="upsell_description" rows="2" maxlength="400" class="{{ $fldCls }}">{{ $offers['upsell_description'] }}</textarea></div>
                            </div>

                            {{-- ---- Tracking & Pixels ---- --}}
                            @php
                                $tkPatterns = [];
                                foreach ($trackingProviders as $tp) {
                                    $tkPatterns[$tp['key']] = ['field' => $tp['fields'][0]['key'], 're' => $tp['fields'][0]['pattern']];
                                }
                            @endphp
                            <div class="space-y-3 border-t border-neutral-100 pt-4"
                                x-data="{
                                    tk: @js($tracking),
                                    pat: @js($tkPatterns),
                                    valid(k) { const p = this.pat[k]; const v = (this.tk[k] && this.tk[k][p.field]) || ''; return !p.re || new RegExp(p.re).test(v.trim()); },
                                    statusDot(k) { if (!this.tk[k].enabled) return 'bg-neutral-300'; return this.valid(k) ? 'bg-emerald-500' : 'bg-amber-400'; },
                                    test(k) { window.open('{{ $endpoints['trackingTest'] }}?provider=' + k, 'pptest', 'width=460,height=520'); },
                                }">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ __('Tracking & pixels') }}</p>
                                <p class="text-[11px] text-neutral-400">{{ __('Fire events to your ad platforms from this page only. Save before testing.') }}</p>

                                @foreach ($trackingProviders as $tp)
                                    @php $k = $tp['key']; @endphp
                                    <div class="rounded-lg border border-neutral-200 p-3">
                                        <div class="flex items-center justify-between">
                                            <span class="flex items-center gap-2 text-sm font-medium text-neutral-800">
                                                <span class="h-2 w-2 rounded-full" :class="statusDot('{{ $k }}')"></span>
                                                {{ $tp['label'] }}
                                            </span>
                                            <label class="relative inline-flex cursor-pointer items-center">
                                                <input type="checkbox" name="tracking[{{ $k }}][enabled]" value="1" x-model="tk['{{ $k }}'].enabled" class="peer sr-only" />
                                                <span class="h-5 w-9 rounded-full bg-neutral-200 transition peer-checked:bg-brand-500 after:absolute after:start-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span>
                                            </label>
                                        </div>
                                        <div x-show="tk['{{ $k }}'].enabled" x-cloak class="mt-3 space-y-2">
                                            @foreach ($tp['fields'] as $f)
                                                <div>
                                                    <label class="{{ $lblCls }}">{{ $f['label'] }}</label>
                                                    <input type="{{ $f['secret'] ? 'password' : 'text' }}" autocomplete="off" spellcheck="false"
                                                        name="tracking[{{ $k }}][{{ $f['key'] }}]" x-model="tk['{{ $k }}']['{{ $f['key'] }}']"
                                                        placeholder="{{ $f['hint'] }}" class="{{ $fldCls }} font-mono" />
                                                    @if ($loop->first)
                                                        <p class="mt-1 text-[11px]" x-show="tk['{{ $k }}']['{{ $f['key'] }}']"
                                                            :class="valid('{{ $k }}') ? 'text-emerald-600' : 'text-amber-600'"
                                                            x-text="valid('{{ $k }}') ? '{{ __('Format looks valid') }}' : '{{ __('That doesn’t match the expected format') }}'"></p>
                                                    @elseif ($f['hint'])
                                                        <p class="mt-1 text-[11px] text-neutral-400">{{ $f['hint'] }}</p>
                                                    @endif
                                                </div>
                                            @endforeach
                                            <div class="flex items-center gap-3 pt-1 text-[11px]">
                                                <a href="{{ $tp['docsUrl'] }}" target="_blank" rel="noopener" class="font-medium text-brand-600 hover:underline">{{ __('Setup guide') }}</a>
                                                <button type="button" @click="test('{{ $k }}')" class="font-medium text-neutral-600 hover:underline">{{ __('Send test event') }}</button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                <div x-show="Object.keys(pat).some(k => tk[k].enabled)" x-cloak class="rounded-lg bg-neutral-50 p-3 space-y-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ __('Privacy') }}</p>
                                    <label class="flex items-center gap-2 text-xs text-neutral-700"><input type="checkbox" name="tracking[privacy][cookies]" value="1" x-model="tk.privacy.cookies" class="h-4 w-4 rounded border-neutral-300 text-brand-500" /> {{ __('Allow cookies') }}</label>
                                    <label class="flex items-center gap-2 text-xs text-neutral-700"><input type="checkbox" name="tracking[privacy][consent_required]" value="1" x-model="tk.privacy.consent_required" class="h-4 w-4 rounded border-neutral-300 text-brand-500" /> {{ __('Wait for consent before tracking') }}</label>
                                    <label class="flex items-center gap-2 text-xs text-neutral-700"><input type="checkbox" name="tracking[privacy][anonymize_ip]" value="1" x-model="tk.privacy.anonymize_ip" class="h-4 w-4 rounded border-neutral-300 text-brand-500" /> {{ __('Anonymize IP where supported') }}</label>
                                </div>
                            </div>

                            <button type="submit" class="w-full rounded-lg bg-neutral-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-neutral-800">{{ __('Save settings') }}</button>
                        </form>
                    </div>
                </div>
            </aside>

            {{-- ============ Canvas (inline live preview — no iframe) ============ --}}
            {{-- Scroll lives on this element; we save/restore its scrollTop across
                 re-renders so patching the preview never loses the user's place. --}}
            <main x-ref="scroller" class="flex-1 overflow-auto bg-neutral-100 p-6">
                {{-- Inert stand-in for the checkout form so buy buttons don't error. --}}
                <form id="buy" onsubmit="return false" class="hidden"></form>
                {{-- Compiled block-tree CSS (design tokens + scoped rules) for the preview. --}}
                <style x-ref="previewStyle"></style>
                <div class="mx-auto transition-all duration-200" :style="`max-width:${frameWidth}`">
                    <div x-ref="canvas"
                        @click="onCanvasClick($event)" @dblclick="onCanvasDblClick($event)" @mouseover="onCanvasHover($event)" @mouseleave="clearHover()"
                        class="pp-canvas min-h-[70vh] overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
                        <div class="grid min-h-[70vh] place-items-center text-sm text-neutral-300">{{ __('Loading preview…') }}</div>
                    </div>
                </div>
            </main>

            {{-- ============ Right property panel ============ --}}
            <aside class="flex w-80 flex-col border-l border-neutral-200 bg-white">
                <template x-if="!selected">
                    <div class="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center text-neutral-400">
                        <x-heroicon-o-cursor-arrow-rays class="h-8 w-8" />
                        <p class="text-sm">{{ __('Select a block to edit it') }}</p>
                        <p class="text-xs">{{ __('Click any element in the preview.') }}</p>
                    </div>
                </template>

                <template x-if="selected">
                    <div class="flex flex-1 flex-col overflow-hidden">
                        <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <p class="truncate text-sm font-semibold text-neutral-900" x-text="(selectedSchema && selectedSchema.label) || selected.type"></p>
                                <span x-show="selectionCount > 1" x-cloak class="shrink-0 rounded-full bg-brand-500 px-1.5 py-0.5 text-[10px] font-bold text-white" x-text="selectionCount + ' {{ __('selected') }}'"></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="duplicateSelected()" class="grid h-7 w-7 place-items-center rounded-md text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700" title="{{ __('Duplicate') }}"><x-heroicon-o-document-duplicate class="h-4 w-4" /></button>
                                <button type="button" @click="removeSelected()" class="grid h-7 w-7 place-items-center rounded-md text-neutral-400 hover:bg-rose-50 hover:text-rose-500" title="{{ __('Delete') }}"><x-heroicon-o-trash class="h-4 w-4" /></button>
                            </div>
                        </div>

                        {{-- tabs --}}
                        <div class="flex gap-1 border-b border-neutral-100 p-2">
                            @foreach (['content' => 'Content', 'style' => 'Style', 'advanced' => 'Advanced'] as $t => $lbl)
                                <button type="button" @click="rightTab = '{{ $t }}'" :class="rightTab === '{{ $t }}' ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:bg-neutral-100'" class="{{ $railBtn }}">{{ __($lbl) }}</button>
                            @endforeach
                        </div>

                        <div class="flex-1 space-y-4 overflow-y-auto p-4">
                            {{-- CONTENT tab: fields generated from the block schema --}}
                            <div x-show="rightTab === 'content'" class="space-y-4">
                                <template x-for="field in (selectedSchema && selectedSchema.fields.content) || []" :key="field.key">
                                    <div>
                                        @include('frontend.seller.partials.builder-field')
                                    </div>
                                </template>
                                <template x-if="!(selectedSchema && selectedSchema.fields.content)">
                                    <p class="text-xs text-neutral-400">{{ __('This block has no content options.') }}</p>
                                </template>
                            </div>

                            {{-- STYLE tab: full per-device visual controls (Framer/Webflow-style) --}}
                            @php
                                $inp = 'w-full rounded-lg border border-neutral-200 px-2.5 py-1.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500';
                                $lbl = 'mb-1 block text-[11px] font-medium text-neutral-500';
                                $grp = 'flex w-full cursor-pointer items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-neutral-400 select-none';
                            @endphp
                            <div x-show="rightTab === 'style'" class="space-y-3">
                                <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2">
                                    <p class="text-[11px] font-medium text-neutral-500">{{ __('Editing') }} <span class="font-semibold capitalize text-neutral-800" x-text="device"></span></p>
                                    <button type="button" @click="resetDevice()" class="text-[11px] font-medium text-neutral-400 transition hover:text-rose-500">{{ __('Reset') }}</button>
                                </div>

                                {{-- Layout & size --}}
                                <details open class="border-t border-neutral-100 pt-3">
                                    <summary class="{{ $grp }}">{{ __('Layout & size') }}</summary>
                                    <div class="mt-3 space-y-3">
                                        <div class="grid grid-cols-3 gap-2">
                                            <div><label class="{{ $lbl }}">{{ __('Width') }}</label><input type="text" :value="styleValue(device,'width')" @input="setStyle('width',$event.target.value)" placeholder="auto" class="{{ $inp }}" /></div>
                                            <div><label class="{{ $lbl }}">{{ __('Max W') }}</label><input type="text" :value="styleValue(device,'maxWidth')" @input="setStyle('maxWidth',$event.target.value)" placeholder="—" class="{{ $inp }}" /></div>
                                            <div><label class="{{ $lbl }}">{{ __('Min H') }}</label><input type="number" min="0" :value="styleValue(device,'minHeight')" @input="setStyle('minHeight',$event.target.value)" placeholder="—" class="{{ $inp }}" /></div>
                                        </div>
                                        <div>
                                            <label class="{{ $lbl }}">{{ __('Text align') }}</label>
                                            <select @change="setStyle('align',$event.target.value)" class="{{ $inp }}">
                                                <option value="">{{ __('Default') }}</option>
                                                @foreach (['left' => 'Left', 'center' => 'Center', 'right' => 'Right'] as $v => $l)
                                                    <option value="{{ $v }}" :selected="styleValue(device,'align')==='{{ $v }}'">{{ __($l) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="{{ $lbl }}">{{ __('Justify') }}</label>
                                                <select @change="setStyle('justify',$event.target.value)" class="{{ $inp }}">
                                                    <option value="">{{ __('—') }}</option>
                                                    @foreach (['left' => 'Start', 'center' => 'Center', 'right' => 'End', 'between' => 'Between'] as $v => $l)
                                                        <option value="{{ $v }}" :selected="styleValue(device,'justify')==='{{ $v }}'">{{ __($l) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="{{ $lbl }}">{{ __('Align items') }}</label>
                                                <select @change="setStyle('items',$event.target.value)" class="{{ $inp }}">
                                                    <option value="">{{ __('—') }}</option>
                                                    @foreach (['left' => 'Start', 'center' => 'Center', 'right' => 'End'] as $v => $l)
                                                        <option value="{{ $v }}" :selected="styleValue(device,'items')==='{{ $v }}'">{{ __($l) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div><label class="{{ $lbl }}">{{ __('Gap') }}</label><input type="number" min="0" :value="styleValue(device,'gap')" @input="setStyle('gap',$event.target.value)" placeholder="—" class="{{ $inp }}" /></div>
                                    </div>
                                </details>

                                {{-- Spacing --}}
                                <details open class="border-t border-neutral-100 pt-3">
                                    <summary class="{{ $grp }}">{{ __('Spacing') }}</summary>
                                    <div class="mt-3 space-y-3">
                                        <div>
                                            <p class="{{ $lbl }}">{{ __('Padding') }}</p>
                                            <div class="grid grid-cols-4 gap-1.5">
                                                @foreach (['padTop' => 'T', 'padRight' => 'R', 'padBottom' => 'B', 'padLeft' => 'L'] as $k => $s)
                                                    <input type="number" min="0" title="{{ $s }}" :value="styleValue(device,'{{ $k }}')" @input="setStyle('{{ $k }}',$event.target.value)" placeholder="{{ $s }}" class="{{ $inp }} px-1.5 text-center" />
                                                @endforeach
                                            </div>
                                        </div>
                                        <div>
                                            <p class="{{ $lbl }}">{{ __('Margin') }}</p>
                                            <div class="grid grid-cols-4 gap-1.5">
                                                @foreach (['marginTop' => 'T', 'marginRight' => 'R', 'marginBottom' => 'B', 'marginLeft' => 'L'] as $k => $s)
                                                    <input type="number" title="{{ $s }}" :value="styleValue(device,'{{ $k }}')" @input="setStyle('{{ $k }}',$event.target.value)" placeholder="{{ $s }}" class="{{ $inp }} px-1.5 text-center" />
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </details>

                                {{-- Background --}}
                                <details class="border-t border-neutral-100 pt-3">
                                    <summary class="{{ $grp }}">{{ __('Background') }}</summary>
                                    <div class="mt-3 space-y-3">
                                        <div>
                                            <label class="{{ $lbl }}">{{ __('Colour') }}</label>
                                            <div class="flex gap-1.5">
                                                <input type="text" :value="styleValue(device,'bg')" @input="setStyle('bg',$event.target.value)" placeholder="{{ __('transparent') }}" class="{{ $inp }}" />
                                                <input type="color" :value="styleValue(device,'bg') || '#ffffff'" @input="setStyle('bg',$event.target.value)" class="h-8 w-9 shrink-0 cursor-pointer rounded-lg border border-neutral-200" />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="{{ $lbl }}">{{ __('Image') }}</label>
                                            <template x-if="styleValue(device,'bgImage')">
                                                <div class="flex items-center gap-2">
                                                    <img :src="styleValue(device,'bgImage')" alt="" class="h-9 w-12 shrink-0 rounded border border-neutral-200 object-cover" />
                                                    <button type="button" @click="pickStyleImage('bgImage')" class="flex-1 rounded-lg border border-neutral-200 px-2 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50">{{ __('Change') }}</button>
                                                    <button type="button" @click="setStyle('bgImage','')" class="grid h-7 w-7 shrink-0 place-items-center rounded text-neutral-300 hover:text-rose-500"><x-heroicon-o-x-mark class="h-3.5 w-3.5" /></button>
                                                </div>
                                            </template>
                                            <template x-if="!styleValue(device,'bgImage')">
                                                <button type="button" @click="pickStyleImage('bgImage')" class="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-neutral-300 py-2.5 text-xs font-medium text-neutral-500 transition hover:border-brand-400 hover:text-brand-600"><x-heroicon-o-photo class="h-4 w-4" /> {{ __('Choose image') }}</button>
                                            </template>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="{{ $lbl }}">{{ __('Size') }}</label>
                                                <select @change="setStyle('bgSize',$event.target.value)" class="{{ $inp }}">
                                                    <option value="">{{ __('Cover') }}</option>
                                                    @foreach (['contain' => 'Contain', 'auto' => 'Auto', '100% 100%' => 'Stretch'] as $v => $l)
                                                        <option value="{{ $v }}" :selected="styleValue(device,'bgSize')==='{{ $v }}'">{{ __($l) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div><label class="{{ $lbl }}">{{ __('Position') }}</label><input type="text" :value="styleValue(device,'bgPosition')" @input="setStyle('bgPosition',$event.target.value)" placeholder="center" class="{{ $inp }}" /></div>
                                        </div>
                                        <div><label class="{{ $lbl }}">{{ __('Overlay') }}</label><input type="text" :value="styleValue(device,'overlay')" @input="setStyle('overlay',$event.target.value)" placeholder="rgba(0,0,0,.4)" class="{{ $inp }}" /></div>
                                        <div><label class="{{ $lbl }}">{{ __('Gradient') }}</label><input type="text" :value="styleValue(device,'gradient')" @input="setStyle('gradient',$event.target.value)" placeholder="linear-gradient(…)" class="{{ $inp }}" /></div>
                                        <label class="flex items-center gap-2 text-xs text-neutral-600"><input type="checkbox" :checked="!!styleValue(device,'parallax')" @change="setStyle('parallax',$event.target.checked ? 1 : '')" class="h-3.5 w-3.5 rounded border-neutral-300 text-brand-500" /> {{ __('Parallax (fixed)') }}</label>
                                    </div>
                                </details>

                                {{-- Border & radius --}}
                                <details class="border-t border-neutral-100 pt-3">
                                    <summary class="{{ $grp }}">{{ __('Border & radius') }}</summary>
                                    <div class="mt-3 space-y-3">
                                        <div class="grid grid-cols-2 gap-2">
                                            <div><label class="{{ $lbl }}">{{ __('Width') }}</label><input type="number" min="0" :value="styleValue(device,'borderWidth')" @input="setStyle('borderWidth',$event.target.value)" placeholder="0" class="{{ $inp }}" /></div>
                                            <div>
                                                <label class="{{ $lbl }}">{{ __('Style') }}</label>
                                                <select @change="setStyle('borderStyle',$event.target.value)" class="{{ $inp }}">
                                                    @foreach (['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'] as $v => $l)
                                                        <option value="{{ $v }}" :selected="styleValue(device,'borderStyle')==='{{ $v }}'">{{ __($l) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="{{ $lbl }}">{{ __('Border colour') }}</label>
                                            <div class="flex gap-1.5">
                                                <input type="text" :value="styleValue(device,'borderColor')" @input="setStyle('borderColor',$event.target.value)" placeholder="#e5e7eb" class="{{ $inp }}" />
                                                <input type="color" :value="styleValue(device,'borderColor') || '#e5e7eb'" @input="setStyle('borderColor',$event.target.value)" class="h-8 w-9 shrink-0 cursor-pointer rounded-lg border border-neutral-200" />
                                            </div>
                                        </div>
                                        <div><label class="{{ $lbl }}">{{ __('Corner radius') }}</label><input type="number" min="0" :value="styleValue(device,'radius')" @input="setStyle('radius',$event.target.value)" placeholder="—" class="{{ $inp }}" /></div>
                                    </div>
                                </details>

                                {{-- Effects --}}
                                <details class="border-t border-neutral-100 pt-3">
                                    <summary class="{{ $grp }}">{{ __('Effects') }}</summary>
                                    <div class="mt-3 space-y-3">
                                        <div>
                                            <label class="{{ $lbl }}">{{ __('Shadow') }}</label>
                                            <select @change="setStyle('shadow',$event.target.value)" class="{{ $inp }}">
                                                <option value="">{{ __('None') }}</option>
                                                @foreach (['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large'] as $v => $l)
                                                    <option value="{{ $v }}" :selected="styleValue(device,'shadow')==='{{ $v }}'">{{ __($l) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div><label class="{{ $lbl }}">{{ __('Opacity %') }}</label><input type="number" min="0" max="100" :value="styleValue(device,'opacity')" @input="setStyle('opacity',$event.target.value)" placeholder="100" class="{{ $inp }}" /></div>
                                            <div><label class="{{ $lbl }}">{{ __('Z-index') }}</label><input type="number" :value="styleValue(device,'zIndex')" @input="setStyle('zIndex',$event.target.value)" placeholder="—" class="{{ $inp }}" /></div>
                                        </div>
                                    </div>
                                </details>

                                {{-- Animation (device-independent) --}}
                                <details class="border-t border-neutral-100 pt-3">
                                    <summary class="{{ $grp }}">{{ __('Animation') }}</summary>
                                    <div class="mt-3 space-y-3">
                                        <div>
                                            <label class="{{ $lbl }}">{{ __('On load') }}</label>
                                            <select @change="setBase('anim',$event.target.value)" class="{{ $inp }}">
                                                <option value="">{{ __('None') }}</option>
                                                @foreach (['fade' => 'Fade in', 'up' => 'Slide up', 'down' => 'Slide down', 'left' => 'Slide left', 'right' => 'Slide right', 'zoom' => 'Zoom in'] as $v => $l)
                                                    <option value="{{ $v }}" :selected="baseValue('anim')==='{{ $v }}'">{{ __($l) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div><label class="{{ $lbl }}">{{ __('Duration ms') }}</label><input type="number" min="100" max="4000" step="50" :value="baseValue('animDur')" @input="setBase('animDur',$event.target.value)" placeholder="600" class="{{ $inp }}" /></div>
                                            <div><label class="{{ $lbl }}">{{ __('Delay ms') }}</label><input type="number" min="0" max="5000" step="50" :value="baseValue('animDelay')" @input="setBase('animDelay',$event.target.value)" placeholder="0" class="{{ $inp }}" /></div>
                                        </div>
                                    </div>
                                </details>

                                {{-- Custom CSS (device-independent) --}}
                                <details class="border-t border-neutral-100 pt-3">
                                    <summary class="{{ $grp }}">{{ __('Custom CSS') }}</summary>
                                    <div class="mt-3">
                                        <textarea rows="4" :value="baseValue('customCss')" @input="setBase('customCss',$event.target.value)" placeholder="letter-spacing:.02em; text-transform:uppercase;" class="{{ $inp }} font-mono text-xs"></textarea>
                                        <p class="mt-1 text-[11px] text-neutral-400">{{ __('Declarations only — scoped to this block.') }}</p>
                                    </div>
                                </details>
                            </div>

                            {{-- ADVANCED tab: visibility, anchor, custom class, id --}}
                            <div x-show="rightTab === 'advanced'" class="space-y-4">
                                <div>
                                    <p class="mb-2 text-xs font-medium text-neutral-500">{{ __('Visible on') }}</p>
                                    <div class="flex gap-2">
                                        @foreach (['desktop', 'tablet', 'mobile'] as $d)
                                            <button type="button" @click="toggleVisibility('{{ $d }}')"
                                                :class="selected.visibility.{{ $d }} ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-neutral-200 text-neutral-400 line-through'"
                                                class="flex-1 rounded-lg border px-2 py-1.5 text-xs font-medium capitalize">{{ $d }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Anchor ID') }}</label>
                                    <input type="text" :value="metaValue('anchor')" @input="setMeta('anchor',$event.target.value)" placeholder="pricing" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm" />
                                    <p class="mt-1 text-[11px] text-neutral-400">{{ __('Link to this section with #anchor.') }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Custom CSS class') }}</label>
                                    <input type="text" :value="metaValue('className')" @input="setMeta('className',$event.target.value)" placeholder="my-class another" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Block ID') }}</label>
                                    <input type="text" :value="selected.id" readonly class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 font-mono text-xs text-neutral-500" />
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </aside>
        </div>

        {{-- Template gallery --}}
        <div x-show="showTemplates" x-cloak @keydown.escape.window="showTemplates = false"
            class="fixed inset-0 z-50 grid place-items-center bg-neutral-900/40 p-4" @click.self="showTemplates = false">
            <div class="flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl" x-transition>
                <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900">{{ __('Start from a template') }}</h2>
                        <p class="text-xs text-neutral-400">{{ __('Applying a template replaces your current page. You can undo it.') }}</p>
                    </div>
                    <button type="button" @click="showTemplates = false" class="grid h-7 w-7 place-items-center rounded-md text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                </div>
                <div class="grid flex-1 gap-3 overflow-y-auto p-6 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="t in templates" :key="t.id">
                        <button type="button" @click="applyTemplate(t.id)" :disabled="applyingTemplate"
                            class="group flex flex-col overflow-hidden rounded-2xl border border-neutral-200 text-left transition hover:border-brand-400 hover:shadow-md disabled:opacity-50">
                            {{-- Live scaled "screenshot" of the template (top of the page). --}}
                            <div class="relative h-40 overflow-hidden border-b border-neutral-100 bg-white">
                                <iframe :src="`{{ url('/shop/templates') }}/${t.id}/preview`" loading="lazy" tabindex="-1" aria-hidden="true"
                                    class="pointer-events-none origin-top-left select-none"
                                    style="width:1280px;height:1000px;transform:scale(0.207);border:0"></iframe>
                                <span class="pointer-events-none absolute inset-0"></span>
                            </div>
                            <div class="p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-neutral-900" x-text="t.name"></span>
                                    <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-500" x-text="t.category"></span>
                                </div>
                                <span class="mt-1 block text-xs leading-relaxed text-neutral-500" x-text="t.description"></span>
                                <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-brand-500 opacity-0 transition group-hover:opacity-100">{{ __('Use template') }} <x-heroicon-o-arrow-right class="h-3 w-3" /></span>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- Media Library — picker (from an image field) + manager (from the toolbar) --}}
        <div x-show="media.open" x-cloak @keydown.escape.window="media.picker ? mediaClose() : (media.open = false)"
            class="fixed inset-0 z-[60] grid place-items-center bg-neutral-900/50 p-4">
            <div class="flex h-[86vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-3">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-photo class="h-5 w-5 text-brand-500" />
                        <h2 class="text-sm font-semibold text-neutral-900">{{ __('Media Library') }}</h2>
                        <span class="text-xs text-neutral-400"><span x-text="media.total"></span> {{ __('items') }}</span>
                    </div>
                    <button type="button" @click="media.picker ? mediaClose() : (media.open = false)" class="grid h-7 w-7 place-items-center rounded-md text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                </div>
                <div class="min-h-0 flex-1">
                    @include('frontend.seller.partials.media-panel')
                </div>
            </div>
        </div>

        {{-- Keyboard shortcuts overlay (toggle with ?) --}}
        <div x-show="showShortcuts" x-cloak @keydown.escape.window="showShortcuts = false"
            class="fixed inset-0 z-50 grid place-items-center bg-neutral-900/40 p-4" @click.self="showShortcuts = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" x-transition>
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-neutral-900">{{ __('Keyboard shortcuts') }}</h2>
                    <button type="button" @click="showShortcuts = false" class="grid h-7 w-7 place-items-center rounded-md text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                </div>
                @php
                    $keys = [
                        ['⌘/Ctrl + Z', __('Undo')], ['⌘/Ctrl + ⇧ + Z', __('Redo')],
                        ['⌘/Ctrl + C', __('Copy')], ['⌘/Ctrl + X', __('Cut')], ['⌘/Ctrl + V', __('Paste')],
                        ['⌘/Ctrl + D', __('Duplicate')], ['⌘/Ctrl + A', __('Select all')],
                        ['⌫ / Delete', __('Delete selected')], ['⇧/⌘ + Click', __('Multi-select')],
                        [__('Double-click'), __('Edit text inline')], ['Esc', __('Deselect')],
                    ];
                @endphp
                <dl class="mt-4 space-y-2">
                    @foreach ($keys as [$k, $desc])
                        <div class="flex items-center justify-between text-sm">
                            <dt class="text-neutral-600">{{ $desc }}</dt>
                            <dd><kbd class="rounded-md border border-neutral-200 bg-neutral-50 px-2 py-0.5 font-mono text-xs text-neutral-500">{{ $k }}</kbd></dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-4 text-center text-xs text-neutral-400">{{ __('Tip: double-click any headline on the canvas to edit it in place.') }}</p>
            </div>
        </div>
    </div>
</x-layouts.builder>
