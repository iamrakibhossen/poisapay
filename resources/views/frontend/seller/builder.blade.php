@php
    $config = [
        'document' => $document,
        'schemas' => $schemas,
        'palette' => $palette,
        'endpoints' => $endpoints,
        'name' => $name,
        'productId' => (string) $productId,
        'products' => $products,
        'csrf' => csrf_token(),
        'savedAt' => optional($page->updated_at)->toIso8601String(),
    ];
    $railBtn = 'flex-1 rounded-md px-2 py-1.5 text-xs font-semibold transition';
@endphp

<x-layouts.builder :title="$name">
    {{-- Editor affordances for the inline live canvas (hover + selected outlines). --}}
    <style>
        .pp-canvas [id^="b_"]{position:relative}
        .pp-canvas [data-pp-hover]{outline:2px dashed color-mix(in srgb, var(--pp-accent,#2563eb) 55%, transparent);outline-offset:-2px;cursor:pointer}
        .pp-canvas [data-pp-selected]{outline:2px solid var(--pp-accent,#2563eb);outline-offset:-2px}
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
                                <li>
                                    <div @click="select(node.id)" :class="selectedId === node.id ? 'border-brand-400 bg-brand-50' : 'border-transparent hover:bg-neutral-50'"
                                        class="flex items-center gap-2 rounded-md border px-2 py-1.5 text-xs">
                                        <span data-drag class="cursor-grab text-neutral-300 hover:text-neutral-500"><x-heroicon-o-bars-2 class="h-3.5 w-3.5" /></span>
                                        <span class="flex-1 truncate font-medium text-neutral-700" x-text="(schemas[node.type] && schemas[node.type].label) || node.type"></span>
                                        <button type="button" @click.stop="selectedId = node.id; removeSelected()" class="text-neutral-300 hover:text-rose-500"><x-heroicon-o-x-mark class="h-3.5 w-3.5" /></button>
                                    </div>
                                    {{-- one level of nesting for containers --}}
                                    <template x-if="node.children && node.children.length">
                                        <ul :data-parent="node.id" data-sortable class="ml-4 mt-1 space-y-1 border-l border-neutral-100 pl-2">
                                            <template x-for="child in node.children" :key="child.id">
                                                <li @click.stop="select(child.id)" :class="selectedId === child.id ? 'border-brand-400 bg-brand-50' : 'border-transparent hover:bg-neutral-50'"
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
                                <div><label class="{{ $lblCls }}">{{ __('Social image URL') }}</label><input name="seo_og_image" value="{{ $seo['og_image'] }}" class="{{ $fldCls }}" /></div>
                                <label class="flex items-center gap-2 text-sm text-neutral-700"><input type="checkbox" name="seo_noindex" value="1" @checked($seo['noindex']) class="h-4 w-4 rounded border-neutral-300 text-brand-500" /> {{ __('Hide from search engines') }}</label>
                            </div>

                            <div class="space-y-3 border-t border-neutral-100 pt-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ __('Order bump') }} <span class="normal-case text-neutral-400">· {{ __('at checkout') }}</span></p>
                                <select name="bump_product_id" class="{{ $fldCls }}">
                                    <option value="">{{ __('No order bump') }}</option>
                                    @foreach ($products as $id => $pname)
                                        <option value="{{ $id }}" @selected($offers['bump_product_id'] === (string) $id)>{{ $pname }}</option>
                                    @endforeach
                                </select>
                                <div class="grid grid-cols-2 gap-2">
                                    <div><label class="{{ $lblCls }}">{{ __('Price') }} {{ $offers['currency'] }}</label><input name="bump_price" value="{{ $offers['bump_price'] }}" inputmode="decimal" class="{{ $fldCls }}" /></div>
                                    <div><label class="{{ $lblCls }}">{{ __('Headline') }}</label><input name="bump_headline" value="{{ $offers['bump_headline'] }}" class="{{ $fldCls }}" /></div>
                                </div>
                            </div>

                            <div class="space-y-3 border-t border-neutral-100 pt-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ __('1-click upsell') }} <span class="normal-case text-neutral-400">· {{ __('after purchase') }}</span></p>
                                <select name="upsell_product_id" class="{{ $fldCls }}">
                                    <option value="">{{ __('No upsell') }}</option>
                                    @foreach ($products as $id => $pname)
                                        <option value="{{ $id }}" @selected($offers['upsell_product_id'] === (string) $id)>{{ $pname }}</option>
                                    @endforeach
                                </select>
                                <div class="grid grid-cols-2 gap-2">
                                    <div><label class="{{ $lblCls }}">{{ __('Price') }} {{ $offers['currency'] }}</label><input name="upsell_price" value="{{ $offers['upsell_price'] }}" inputmode="decimal" class="{{ $fldCls }}" /></div>
                                    <div><label class="{{ $lblCls }}">{{ __('Headline') }}</label><input name="upsell_headline" value="{{ $offers['upsell_headline'] }}" class="{{ $fldCls }}" /></div>
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
                        @click="onCanvasClick($event)" @mouseover="onCanvasHover($event)" @mouseleave="clearHover()"
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
                            <p class="text-sm font-semibold text-neutral-900" x-text="(selectedSchema && selectedSchema.label) || selected.type"></p>
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

                            {{-- STYLE tab: per-device spacing/alignment/colours --}}
                            <div x-show="rightTab === 'style'" class="space-y-4">
                                <p class="text-[11px] font-medium text-neutral-400">{{ __('Editing') }}: <span class="font-semibold text-neutral-600" x-text="device"></span></p>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Vertical padding') }}</label>
                                    <input type="number" min="0" max="200" :value="styleValue(device, 'padY')" @input="setStyle('padY', $event.target.value)" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm" placeholder="—" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Text align') }}</label>
                                    <select @change="setStyle('align', $event.target.value)" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                        <option value="">{{ __('Default') }}</option>
                                        <option value="left" :selected="styleValue(device,'align')==='left'">{{ __('Left') }}</option>
                                        <option value="center" :selected="styleValue(device,'align')==='center'">{{ __('Center') }}</option>
                                        <option value="right" :selected="styleValue(device,'align')==='right'">{{ __('Right') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Background') }}</label>
                                    <input type="text" :value="styleValue(device, 'bg')" @input="setStyle('bg', $event.target.value)" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm" placeholder="#ffffff or empty" />
                                </div>
                            </div>

                            {{-- ADVANCED tab: per-device visibility + id --}}
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
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Block ID') }}</label>
                                    <input type="text" :value="selected.id" readonly class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 font-mono text-xs text-neutral-500" />
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </aside>
        </div>
    </div>
</x-layouts.builder>
