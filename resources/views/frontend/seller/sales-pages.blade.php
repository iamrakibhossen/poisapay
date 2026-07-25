@php
    // Live product behind this page — seeds the Product section + hero so the
    // builder opens showing the real product, not placeholder copy.
    $pi = $productInfo ?? [];
    $heroSeed = [
        'headline' => $pi['name'] ?? 'Your product name',
        'tagline' => $pi['summary'] ?? 'A short, punchy line that sells the outcome.',
        'desc' => $pi['description'] ?: 'Describe what the buyer gets and why it’s worth it.',
        'btn' => 'Buy now',
        'price' => $pi['price'] ?? '—',
        'compare' => $pi['compare'] ?? '',
    ];
    $productSeed = [
        'name' => $pi['name'] ?? 'Your product',
        'type' => $pi['type'] ?? 'Digital download',
        'summary' => $pi['summary'] ?: 'What this product includes and why it’s worth it.',
        'price' => $pi['price'] ?? '—',
        'compare' => $pi['compare'] ?? '',
        'btn' => 'Buy now',
        'note' => 'Instant, secure checkout — wallet, card, crypto, bank & mobile money.',
    ];
    $brand = $pi['seller'] ?: __('Your store');

    // Section catalog — label + icon for the sections list and the "Add section" menu.
    $catalog = [
        'hero' => ['Hero', 'sparkles'],
        'product' => ['Product', 'cube'],
        'stats' => ['Stats', 'chart-bar'],
        'logos' => ['Trusted by', 'building-office-2'],
        'features' => ['Features', 'squares-2x2'],
        'benefits' => ['Benefits', 'check-badge'],
        'steps' => ['How it works', 'list-bullet'],
        'bonuses' => ["What’s included", 'gift'],
        'video' => ['Video', 'play-circle'],
        'testimonials' => ['Testimonials', 'chat-bubble-left-ellipsis'],
        'faq' => ['FAQ', 'question-mark-circle'],
        'guarantee' => ['Guarantee', 'shield-check'],
        'countdown' => ['Countdown', 'clock'],
        'cta' => ['Final CTA', 'megaphone'],
    ];
@endphp
<x-layouts.app :title="__('Sales page')">
    <form method="POST" action="{{ route('sell.sales-page.update', ['slug' => $slug]) }}" class="mt-6"
        x-data="{
            device: 'desktop',
            adding: false,
            accent: '#2563eb',
            btn: 'rounded',
            font: 'Inter',
            name: @js($seed['name'] ?? __('Untitled page')),
            editing: null,
            sections: { hero: true, product: true, stats: true, logos: false, features: true, benefits: true, steps: false, bonuses: false, video: false, testimonials: true, faq: true, guarantee: true, countdown: false, cta: true },
            order: ['hero', 'product', 'stats', 'features', 'benefits', 'testimonials', 'faq', 'guarantee', 'cta'],
            labels: @js(collect($catalog)->map(fn ($c) => $c[0])),
            /* Default content per section — the source addSection() copies from. */
            defaults: {
                hero: @js($heroSeed),
                product: @js($productSeed),
                stats: [{ value: '12,400+', label: 'Happy customers' }, { value: '4.9/5', label: 'Average rating' }, { value: '60+', label: 'Countries' }, { value: '24/7', label: 'Support' }],
                logos: ['Acme Inc', 'Globex', 'Umbrella', 'Initech', 'Hooli'],
                features: ['Auth & teams', 'Billing built in', 'Beautiful UI', 'DX first'],
                benefits: ['Save 100+ hours of setup', 'Clean, tested codebase', 'Lifetime updates', '6 months of support'],
                steps: [{ title: 'Buy in seconds', desc: 'Checkout with wallet, card, crypto, bank or mobile money.' }, { title: 'Get instant access', desc: 'Your files, keys or membership arrive right away.' }, { title: 'Start winning', desc: 'Put it to work and see results fast.' }],
                bonuses: [{ title: 'Quick-start guide', value: '$29' }, { title: 'Private community access', value: '$99' }, { title: 'Lifetime updates', value: '$199' }],
                video: { title: 'Watch the 2-minute demo', subtitle: 'See exactly what you get before you buy.' },
                testimonials: [{ name: 'Aisha K.', role: 'Indie founder', quote: 'I launched my MVP in 4 days — paid for itself instantly.' }, { name: 'Tanvir H.', role: 'Agency lead', quote: 'Huge time saver on every client project.' }],
                faq: [{ q: 'What do I get?', a: 'Instant access to everything listed above, delivered right after payment.' }, { q: 'Which license?', a: 'A single-seat license; team licenses available on request.' }, { q: 'Refund policy?', a: '14-day money-back guarantee, no questions asked.' }],
                guarantee: '14-day money-back guarantee — buy with complete confidence.',
                countdown: 'Offer ends in',
                cta: { heading: 'Start today', btn: 'Get instant access' },
            },
            content: {
                hero: @js($heroSeed),
                product: @js($productSeed),
                stats: [{ value: '12,400+', label: 'Happy customers' }, { value: '4.9/5', label: 'Average rating' }, { value: '60+', label: 'Countries' }, { value: '24/7', label: 'Support' }],
                logos: ['Acme Inc', 'Globex', 'Umbrella', 'Initech', 'Hooli'],
                features: ['Auth & teams', 'Billing built in', 'Beautiful UI', 'DX first'],
                benefits: ['Save 100+ hours of setup', 'Clean, tested codebase', 'Lifetime updates', '6 months of support'],
                steps: [{ title: 'Buy in seconds', desc: 'Checkout with wallet, card, crypto, bank or mobile money.' }, { title: 'Get instant access', desc: 'Your files, keys or membership arrive right away.' }, { title: 'Start winning', desc: 'Put it to work and see results fast.' }],
                bonuses: [{ title: 'Quick-start guide', value: '$29' }, { title: 'Private community access', value: '$99' }, { title: 'Lifetime updates', value: '$199' }],
                video: { title: 'Watch the 2-minute demo', subtitle: 'See exactly what you get before you buy.' },
                testimonials: [{ name: 'Aisha K.', role: 'Indie founder', quote: 'I launched my MVP in 4 days — paid for itself instantly.' }, { name: 'Tanvir H.', role: 'Agency lead', quote: 'Huge time saver on every client project.' }],
                faq: [{ q: 'What do I get?', a: 'Instant access to everything listed above, delivered right after payment.' }, { q: 'Which license?', a: 'A single-seat license; team licenses available on request.' }, { q: 'Refund policy?', a: '14-day money-back guarantee, no questions asked.' }],
                guarantee: '14-day money-back guarantee — buy with complete confidence.',
                countdown: 'Offer ends in',
                cta: { heading: 'Start today', btn: 'Get instant access' },
            },
            productsInfo: @js($productsInfo ?? []),
            get btnRadius() { return this.btn === 'pill' ? '9999px' : this.btn === 'square' ? '2px' : '12px'; },
            move(i, d) { const j = i + d; if (j < 0 || j >= this.order.length) return; [this.order[i], this.order[j]] = [this.order[j], this.order[i]]; },
            edit(key) { this.sections[key] = true; this.editing = key; },
            copy(v) { return JSON.parse(JSON.stringify(v)); },
            addSection(key) {
                if (this.content[key] === undefined && this.defaults[key] !== undefined) this.content[key] = this.copy(this.defaults[key]);
                if (! this.order.includes(key)) this.order.push(key);
                this.sections[key] = true;
                this.adding = false;
                this.edit(key);
            },
            removeSection(key) { this.order = this.order.filter(k => k !== key); if (this.editing === key) this.editing = null; },
            addItem(key, blank) { if (! Array.isArray(this.content[key])) this.content[key] = []; this.content[key].push(typeof blank === 'object' ? this.copy(blank) : blank); },
            removeItem(key, i) { this.content[key].splice(i, 1); },
            /* Repoint the page at another product → resync the hero + product-section copy to it. */
            switchProduct(id) {
                const p = this.productsInfo[id];
                if (! p) return;
                this.content.hero.headline = p.name ?? this.content.hero.headline;
                this.content.hero.tagline = p.summary ?? '';
                this.content.hero.desc = p.description ?? '';
                this.content.hero.price = p.price ?? '—';
                this.content.hero.compare = p.compare ?? '';
                this.content.product.name = p.name ?? this.content.product.name;
                this.content.product.type = p.type ?? this.content.product.type;
                this.content.product.summary = p.summary ?? '';
                this.content.product.price = p.price ?? '—';
                this.content.product.compare = p.compare ?? '';
            },
            get builderJson() { return JSON.stringify({ theme: { accent: this.accent, btn: this.btn, font: this.font }, sections: this.order.map(k => ({ type: k, enabled: !! this.sections[k], content: this.content[k] ?? null })) }); },
            ...@js($seed),
        }"
        :style="{ '--accent': accent }">
        @csrf
        <input type="hidden" name="name" :value="name" />
        <input type="hidden" name="builder" :value="builderJson" />

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <a href="{{ route('sell.sales-pages') }}" class="inline-flex items-center gap-1 text-xs font-medium text-neutral-500 transition hover:text-neutral-900">
                    <x-heroicon-o-chevron-left class="h-3.5 w-3.5" /> {{ __('Sales pages') }}
                </a>
                <input x-model="name" maxlength="120"
                    class="mt-1 w-full max-w-md rounded-md border border-transparent bg-transparent px-1 py-0.5 text-xl font-semibold tracking-tight text-neutral-900 transition hover:border-neutral-200 focus:border-brand-500 focus:bg-white focus:ring-1 focus:ring-brand-500"
                    :aria-label="'{{ __('Page name') }}'" />
                <p class="px-1 text-xs text-neutral-500">
                    {{ $product }} · <span class="font-mono">/p/{{ $slug }}</span>
                    @if ($published)
                        <span class="ms-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> {{ __('Live') }}</span>
                    @else
                        <span class="ms-1 inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold text-neutral-500">{{ __('Draft') }}</span>
                    @endif
                </p>
                @error('publish')<p class="px-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-center gap-2">
                <div class="flex rounded-lg border border-neutral-200 p-0.5">
                    <button type="button" x-on:click="device = 'desktop'" :class="device === 'desktop' ? 'bg-neutral-900 text-white' : 'text-neutral-500'" class="grid h-7 w-8 place-items-center rounded-md transition"><x-heroicon-o-computer-desktop class="h-4 w-4" /></button>
                    <button type="button" x-on:click="device = 'mobile'" :class="device === 'mobile' ? 'bg-neutral-900 text-white' : 'text-neutral-500'" class="grid h-7 w-8 place-items-center rounded-md transition"><x-heroicon-o-device-phone-mobile class="h-4 w-4" /></button>
                </div>
                @if ($published)
                    <x-ui.button href="{{ route('funnel.sales', ['slug' => $slug]) }}" target="_blank" variant="secondary" size="sm" icon="arrow-top-right-on-square">{{ __('View live') }}</x-ui.button>
                @endif
                {{-- Save keeps the page as-is; Publish (formaction) persists + goes live. --}}
                <x-ui.button type="submit" variant="secondary" size="sm" icon="check">{{ __('Save') }}</x-ui.button>
                <button type="submit" formaction="{{ route('sell.sales-page.publish', ['slug' => $slug]) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-semibold text-white transition {{ $published ? 'bg-neutral-700 hover:bg-neutral-800' : 'bg-brand-500 hover:bg-brand-600' }}">
                    @if ($published)
                        <x-heroicon-o-eye-slash class="h-4 w-4" /> {{ __('Unpublish') }}
                    @else
                        <x-heroicon-o-rocket-launch class="h-4 w-4" /> {{ __('Publish') }}
                    @endif
                </button>
            </div>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-[340px_1fr]">
            {{-- ============ Left: controls ============ --}}
            <div class="space-y-4">

                {{-- ---- Sections list (when nothing is being edited) ---- --}}
                <div x-show="editing === null">
                    {{-- Product this page sells — switchable (only the seller's own products). --}}
                    <x-ui.card class="mb-4">
                        <p class="mb-1 text-sm font-semibold text-neutral-900">{{ __('Product') }}</p>
                        <p class="mb-3 text-xs text-neutral-500">{{ __('What this page sells. Changing it repoints checkout — save to refresh the preview.') }}</p>
                        <select name="product_id" x-on:change="switchProduct($event.target.value)"
                            class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                            @foreach ($products as $id => $pname)
                                <option value="{{ $id }}" @selected((string) $page->product_id === (string) $id)>{{ $pname }}</option>
                            @endforeach
                        </select>
                    </x-ui.card>

                    {{-- Offers: order bump (checkout) + 1-click upsell (thank-you) --}}
                    <x-ui.card class="mb-4" x-data="{ open: {{ ($offers['bump_product_id'] || $offers['upsell_product_id']) ? 'true' : 'false' }} }">
                        <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between">
                            <span>
                                <span class="block text-left text-sm font-semibold text-neutral-900">{{ __('Offers') }} <span class="ms-1 rounded-full bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold text-brand-600">{{ __('boost revenue') }}</span></span>
                                <span class="block text-left text-xs text-neutral-500">{{ __('Order bump at checkout + 1-click upsell after.') }}</span>
                            </span>
                            <x-heroicon-o-chevron-down class="h-4 w-4 text-neutral-400 transition" x-bind:class="open && 'rotate-180'" />
                        </button>

                        <div x-show="open" x-cloak class="mt-4 space-y-5">
                            {{-- Order bump --}}
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Order bump') }} <span class="normal-case text-neutral-400">· {{ __('shown at checkout') }}</span></p>
                                <select name="bump_product_id" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                                    <option value="">{{ __('No order bump') }}</option>
                                    @foreach ($products as $id => $pname)
                                        @continue((string) $id === (string) $page->product_id)
                                        <option value="{{ $id }}" @selected($offers['bump_product_id'] === $id)>{{ $pname }}</option>
                                    @endforeach
                                </select>
                                <div class="grid grid-cols-2 gap-2">
                                    <input name="bump_price" value="{{ $offers['bump_price'] }}" type="number" step="0.01" placeholder="{{ __('Price') }} ({{ $currencySymbol }})" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                    <input name="bump_headline" value="{{ $offers['bump_headline'] }}" maxlength="160" placeholder="{{ __('Headline') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                </div>
                                <input name="bump_description" value="{{ $offers['bump_description'] }}" maxlength="400" placeholder="{{ __('Short description (optional)') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                            </div>

                            {{-- Upsell --}}
                            <div class="space-y-2 border-t border-neutral-100 pt-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('1-click upsell') }} <span class="normal-case text-neutral-400">· {{ __('shown after purchase') }}</span></p>
                                <select name="upsell_product_id" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                                    <option value="">{{ __('No upsell') }}</option>
                                    @foreach ($products as $id => $pname)
                                        @continue((string) $id === (string) $page->product_id)
                                        <option value="{{ $id }}" @selected($offers['upsell_product_id'] === $id)>{{ $pname }}</option>
                                    @endforeach
                                </select>
                                <div class="grid grid-cols-2 gap-2">
                                    <input name="upsell_price" value="{{ $offers['upsell_price'] }}" type="number" step="0.01" placeholder="{{ __('Price') }} ({{ $currencySymbol }})" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                    <input name="upsell_headline" value="{{ $offers['upsell_headline'] }}" maxlength="160" placeholder="{{ __('Headline') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                </div>
                                <input name="upsell_description" value="{{ $offers['upsell_description'] }}" maxlength="400" placeholder="{{ __('Short description (optional)') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                            </div>
                            <p class="text-[11px] text-neutral-400">{{ __('Offer products must use the same currency as this product. Leave price blank to use the product’s own price.') }}</p>
                        </div>
                    </x-ui.card>

                    <x-ui.card>
                        <div class="mb-3 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-neutral-900">{{ __('Sections') }}</p>
                                <p class="text-xs text-neutral-500">{{ __('Drag order with arrows · click to edit · toggle to show/hide.') }}</p>
                            </div>
                            <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-500" x-text="order.filter(k => sections[k]).length + '/' + order.length"></span>
                        </div>
                        <div class="space-y-1.5">
                            <template x-for="(key, i) in order" :key="key">
                                <div class="group flex items-center gap-2 rounded-lg border border-neutral-200 px-2 py-1.5" :class="! sections[key] && 'opacity-60'">
                                    <div class="flex flex-col">
                                        <button type="button" x-on:click="move(i, -1)" :disabled="i === 0" class="text-neutral-300 transition hover:text-neutral-600 disabled:opacity-30"><x-heroicon-o-chevron-up class="h-3 w-3" /></button>
                                        <button type="button" x-on:click="move(i, 1)" :disabled="i === order.length - 1" class="text-neutral-300 transition hover:text-neutral-600 disabled:opacity-30"><x-heroicon-o-chevron-down class="h-3 w-3" /></button>
                                    </div>
                                    <button type="button" x-on:click="edit(key)" class="flex-1 text-left text-sm text-neutral-700 hover:text-brand-700" x-text="labels[key]"></button>
                                    <button type="button" x-on:click="edit(key)" class="rounded-md p-1 text-neutral-300 transition hover:bg-neutral-100 hover:text-neutral-600"><x-heroicon-o-pencil-square class="h-3.5 w-3.5" /></button>
                                    <button type="button" x-show="! ['hero','product'].includes(key)" x-on:click="removeSection(key)" class="rounded-md p-1 text-neutral-300 opacity-0 transition hover:bg-rose-50 hover:text-rose-500 group-hover:opacity-100" title="{{ __('Remove') }}"><x-heroicon-o-x-mark class="h-3.5 w-3.5" /></button>
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input type="checkbox" x-model="sections[key]" class="peer sr-only" />
                                        <span class="h-5 w-9 rounded-full bg-neutral-200 transition peer-checked:bg-brand-500"></span>
                                        <span class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-4"></span>
                                    </label>
                                </div>
                            </template>
                        </div>

                        {{-- Add section --}}
                        <div class="mt-3">
                            <button type="button" x-on:click="adding = ! adding" class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700">
                                <x-heroicon-o-plus-circle class="h-4 w-4" /> {{ __('Add section') }}
                            </button>
                            <div x-show="adding" x-cloak class="mt-2 grid grid-cols-2 gap-1.5">
                                @foreach ($catalog as $key => [$label, $icon])
                                    @continue(in_array($key, ['hero', 'product']))
                                    <button type="button" x-show="! order.includes('{{ $key }}')" x-on:click="addSection('{{ $key }}')"
                                        class="flex items-center gap-2 rounded-lg border border-neutral-200 px-2.5 py-2 text-left text-xs font-medium text-neutral-600 transition hover:border-brand-300 hover:bg-brand-50/50">
                                        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4 shrink-0 text-neutral-400" /> {{ $label }}
                                    </button>
                                @endforeach
                                <p class="col-span-2 text-center text-[11px] text-neutral-300" x-show="order.length >= {{ count($catalog) }}">{{ __('All sections added.') }}</p>
                            </div>
                        </div>
                    </x-ui.card>

                    {{-- Theme --}}
                    <x-ui.card class="mt-4">
                        <p class="mb-3 text-sm font-semibold text-neutral-900">{{ __('Theme') }}</p>
                        <p class="mb-2 text-xs font-medium text-neutral-500">{{ __('Accent color') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($themes as $hex => $name)
                                <button type="button" x-on:click="accent = '{{ $hex }}'" :class="accent === '{{ $hex }}' && 'ring-2 ring-offset-2 ring-neutral-900'" class="h-7 w-7 rounded-full transition" style="background: {{ $hex }}" title="{{ $name }}"></button>
                            @endforeach
                            <label class="grid h-7 w-7 cursor-pointer place-items-center rounded-full border border-dashed border-neutral-300 text-neutral-400"><x-heroicon-o-plus class="h-3.5 w-3.5" /><input type="color" x-model="accent" class="sr-only" /></label>
                        </div>
                        <p class="mb-2 mt-4 text-xs font-medium text-neutral-500">{{ __('Button style') }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (['rounded' => 'Rounded', 'pill' => 'Pill', 'square' => 'Square'] as $val => $s)
                                <button type="button" x-on:click="btn = '{{ $val }}'" :class="btn === '{{ $val }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-neutral-200 text-neutral-600'" class="rounded-lg border px-2 py-1.5 text-xs transition">{{ $s }}</button>
                            @endforeach
                        </div>
                        <p class="mb-2 mt-4 text-xs font-medium text-neutral-500">{{ __('Font') }}</p>
                        <div class="flex gap-2">
                            @foreach (['Inter', 'Poppins', 'Georgia'] as $f)
                                <button type="button" x-on:click="font = '{{ $f }}'" :class="font === '{{ $f }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-neutral-200 text-neutral-600'" class="rounded-lg border px-3 py-1.5 text-xs transition" style="font-family: {{ $f }}">{{ $f }}</button>
                            @endforeach
                        </div>
                    </x-ui.card>
                </div>

                {{-- ---- Section content editor (when a section is selected) ---- --}}
                <div x-show="editing !== null" x-cloak>
                    <x-ui.card>
                        <button type="button" x-on:click="editing = null" class="mb-3 inline-flex items-center gap-1 text-xs font-medium text-neutral-500 hover:text-neutral-900">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('All sections') }}
                        </button>
                        <p class="mb-4 text-sm font-semibold text-neutral-900"><span x-text="editing && labels[editing]"></span> {{ __('content') }}</p>

                        @php
                            $lbl = 'mb-1 block text-xs font-medium text-neutral-500';
                            $inp = 'w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500';
                            $addBtn = 'mt-1 inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700';
                        @endphp

                        {{-- HERO --}}
                        <div x-show="editing === 'hero'" class="space-y-3">
                            <div><label class="{{ $lbl }}">{{ __('Headline') }}</label><input class="{{ $inp }}" x-model="content.hero.headline" /></div>
                            <div><label class="{{ $lbl }}">{{ __('Tagline') }}</label><input class="{{ $inp }}" x-model="content.hero.tagline" /></div>
                            <div><label class="{{ $lbl }}">{{ __('Description') }}</label><textarea rows="3" class="{{ $inp }}" x-model="content.hero.desc"></textarea></div>
                            <div class="grid grid-cols-3 gap-2">
                                <div><label class="{{ $lbl }}">{{ __('Button') }}</label><input class="{{ $inp }}" x-model="content.hero.btn" /></div>
                                <div><label class="{{ $lbl }}">{{ __('Price') }}</label><input class="{{ $inp }}" x-model="content.hero.price" /></div>
                                <div><label class="{{ $lbl }}">{{ __('Compare') }}</label><input class="{{ $inp }}" x-model="content.hero.compare" /></div>
                            </div>
                        </div>

                        {{-- PRODUCT --}}
                        <div x-show="editing === 'product'" class="space-y-3">
                            <div class="flex items-start gap-2 rounded-lg border border-brand-100 bg-brand-50/50 p-2.5 text-[11px] text-brand-700">
                                <x-heroicon-o-cube class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <span>{{ __('Seeded from your product. Editing here only changes this page’s copy — not the product itself.') }}</span>
                            </div>
                            <div><label class="{{ $lbl }}">{{ __('Name') }}</label><input class="{{ $inp }}" x-model="content.product.name" /></div>
                            <div><label class="{{ $lbl }}">{{ __('Summary') }}</label><textarea rows="2" class="{{ $inp }}" x-model="content.product.summary"></textarea></div>
                            <div class="grid grid-cols-3 gap-2">
                                <div><label class="{{ $lbl }}">{{ __('Price') }}</label><input class="{{ $inp }}" x-model="content.product.price" /></div>
                                <div><label class="{{ $lbl }}">{{ __('Compare') }}</label><input class="{{ $inp }}" x-model="content.product.compare" /></div>
                                <div><label class="{{ $lbl }}">{{ __('Button') }}</label><input class="{{ $inp }}" x-model="content.product.btn" /></div>
                            </div>
                            <div><label class="{{ $lbl }}">{{ __('Note') }}</label><input class="{{ $inp }}" x-model="content.product.note" /></div>
                        </div>

                        {{-- STATS --}}
                        <div x-show="editing === 'stats'" class="space-y-2">
                            <template x-for="(s, i) in content.stats" :key="i">
                                <div class="flex items-center gap-2">
                                    <input class="{{ $inp }} w-24" x-model="content.stats[i].value" placeholder="12k+" />
                                    <input class="{{ $inp }}" x-model="content.stats[i].label" placeholder="Customers" />
                                    <button type="button" x-on:click="removeItem('stats', i)" class="shrink-0 rounded-md p-1.5 text-neutral-300 hover:bg-rose-50 hover:text-rose-500"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                </div>
                            </template>
                            <button type="button" x-on:click="addItem('stats', { value: '', label: '' })" class="{{ $addBtn }}"><x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add stat') }}</button>
                        </div>

                        {{-- LOGOS --}}
                        <div x-show="editing === 'logos'" class="space-y-2">
                            <template x-for="(l, i) in content.logos" :key="i">
                                <div class="flex items-center gap-2">
                                    <input class="{{ $inp }}" x-model="content.logos[i]" placeholder="Brand name" />
                                    <button type="button" x-on:click="removeItem('logos', i)" class="shrink-0 rounded-md p-1.5 text-neutral-300 hover:bg-rose-50 hover:text-rose-500"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                </div>
                            </template>
                            <button type="button" x-on:click="addItem('logos', '')" class="{{ $addBtn }}"><x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add brand') }}</button>
                        </div>

                        {{-- FEATURES --}}
                        <div x-show="editing === 'features'" class="space-y-2">
                            <template x-for="(f, i) in content.features" :key="i">
                                <div class="flex items-center gap-2">
                                    <input class="{{ $inp }}" x-model="content.features[i]" />
                                    <button type="button" x-on:click="removeItem('features', i)" class="shrink-0 rounded-md p-1.5 text-neutral-300 hover:bg-rose-50 hover:text-rose-500"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                </div>
                            </template>
                            <button type="button" x-on:click="addItem('features', '')" class="{{ $addBtn }}"><x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add feature') }}</button>
                        </div>

                        {{-- BENEFITS --}}
                        <div x-show="editing === 'benefits'" class="space-y-2">
                            <template x-for="(b, i) in content.benefits" :key="i">
                                <div class="flex items-center gap-2">
                                    <input class="{{ $inp }}" x-model="content.benefits[i]" />
                                    <button type="button" x-on:click="removeItem('benefits', i)" class="shrink-0 rounded-md p-1.5 text-neutral-300 hover:bg-rose-50 hover:text-rose-500"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                </div>
                            </template>
                            <button type="button" x-on:click="addItem('benefits', '')" class="{{ $addBtn }}"><x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add benefit') }}</button>
                        </div>

                        {{-- STEPS --}}
                        <div x-show="editing === 'steps'" class="space-y-2">
                            <template x-for="(s, i) in content.steps" :key="i">
                                <div class="rounded-lg border border-neutral-200 p-2.5 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700" x-text="i + 1"></span>
                                        <input class="{{ $inp }}" x-model="content.steps[i].title" placeholder="Step title" />
                                        <button type="button" x-on:click="removeItem('steps', i)" class="shrink-0 rounded-md p-1.5 text-neutral-300 hover:bg-rose-50 hover:text-rose-500"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                    </div>
                                    <textarea rows="2" class="{{ $inp }}" x-model="content.steps[i].desc" placeholder="Describe this step"></textarea>
                                </div>
                            </template>
                            <button type="button" x-on:click="addItem('steps', { title: '', desc: '' })" class="{{ $addBtn }}"><x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add step') }}</button>
                        </div>

                        {{-- BONUSES --}}
                        <div x-show="editing === 'bonuses'" class="space-y-2">
                            <template x-for="(b, i) in content.bonuses" :key="i">
                                <div class="flex items-center gap-2">
                                    <input class="{{ $inp }}" x-model="content.bonuses[i].title" placeholder="What's included" />
                                    <input class="{{ $inp }} w-24" x-model="content.bonuses[i].value" placeholder="$49" />
                                    <button type="button" x-on:click="removeItem('bonuses', i)" class="shrink-0 rounded-md p-1.5 text-neutral-300 hover:bg-rose-50 hover:text-rose-500"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                </div>
                            </template>
                            <button type="button" x-on:click="addItem('bonuses', { title: '', value: '' })" class="{{ $addBtn }}"><x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add item') }}</button>
                        </div>

                        {{-- VIDEO --}}
                        <div x-show="editing === 'video'" class="space-y-3">
                            <div><label class="{{ $lbl }}">{{ __('Title') }}</label><input class="{{ $inp }}" x-model="content.video.title" /></div>
                            <div><label class="{{ $lbl }}">{{ __('Subtitle') }}</label><input class="{{ $inp }}" x-model="content.video.subtitle" /></div>
                        </div>

                        {{-- TESTIMONIALS --}}
                        <div x-show="editing === 'testimonials'" class="space-y-2">
                            <template x-for="(t, i) in content.testimonials" :key="i">
                                <div class="rounded-lg border border-neutral-200 p-2.5 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <input class="{{ $inp }}" x-model="content.testimonials[i].name" placeholder="Name" />
                                        <input class="{{ $inp }}" x-model="content.testimonials[i].role" placeholder="Role (optional)" />
                                        <button type="button" x-on:click="removeItem('testimonials', i)" class="shrink-0 rounded-md p-1.5 text-neutral-300 hover:bg-rose-50 hover:text-rose-500"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                    </div>
                                    <textarea rows="2" class="{{ $inp }}" x-model="content.testimonials[i].quote" placeholder="Quote"></textarea>
                                </div>
                            </template>
                            <button type="button" x-on:click="addItem('testimonials', { name: '', role: '', quote: '' })" class="{{ $addBtn }}"><x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add testimonial') }}</button>
                        </div>

                        {{-- FAQ --}}
                        <div x-show="editing === 'faq'" class="space-y-2">
                            <template x-for="(item, i) in content.faq" :key="i">
                                <div class="rounded-lg border border-neutral-200 p-2.5 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <input class="{{ $inp }}" x-model="content.faq[i].q" placeholder="Question" />
                                        <button type="button" x-on:click="removeItem('faq', i)" class="shrink-0 rounded-md p-1.5 text-neutral-300 hover:bg-rose-50 hover:text-rose-500"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
                                    </div>
                                    <textarea rows="2" class="{{ $inp }}" x-model="content.faq[i].a" placeholder="Answer"></textarea>
                                </div>
                            </template>
                            <button type="button" x-on:click="addItem('faq', { q: '', a: '' })" class="{{ $addBtn }}"><x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add question') }}</button>
                        </div>

                        {{-- GUARANTEE --}}
                        <div x-show="editing === 'guarantee'">
                            <label class="{{ $lbl }}">{{ __('Text') }}</label><textarea rows="2" class="{{ $inp }}" x-model="content.guarantee"></textarea>
                        </div>

                        {{-- COUNTDOWN --}}
                        <div x-show="editing === 'countdown'">
                            <label class="{{ $lbl }}">{{ __('Label') }}</label><input class="{{ $inp }}" x-model="content.countdown" />
                        </div>

                        {{-- CTA --}}
                        <div x-show="editing === 'cta'" class="space-y-3">
                            <div><label class="{{ $lbl }}">{{ __('Heading') }}</label><input class="{{ $inp }}" x-model="content.cta.heading" /></div>
                            <div><label class="{{ $lbl }}">{{ __('Button') }}</label><input class="{{ $inp }}" x-model="content.cta.btn" /></div>
                        </div>
                    </x-ui.card>
                </div>
            </div>

            {{-- ============ Right: live preview (click a section to edit) ============ --}}
            <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-100 shadow-inner">
                <div class="flex items-center gap-1.5 border-b border-neutral-200 bg-white px-4 py-2.5">
                    <span class="h-2.5 w-2.5 rounded-full bg-rose-300"></span><span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span><span class="h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
                    <span class="ms-3 truncate rounded-md bg-neutral-100 px-2 py-0.5 font-mono text-[11px] text-neutral-500">poisahub.com/p/{{ $slug }}</span>
                    <span class="ms-auto text-[11px] text-neutral-400">{{ __('Click a section to edit') }}</span>
                </div>

                <div class="max-h-[74vh] overflow-y-auto p-4">
                    <div class="mx-auto overflow-hidden rounded-xl border border-neutral-200 bg-white transition-all duration-300"
                        :class="device === 'mobile' ? 'max-w-[390px]' : 'max-w-full'" :style="{ fontFamily: font }">

                        <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-3">
                            <span class="text-sm font-bold">{{ $brand }}</span>
                            <button class="px-4 py-2 text-xs font-semibold text-white" :style="{ background: accent, borderRadius: btnRadius }" x-text="content.hero.btn"></button>
                        </div>

                        {{-- Sections: clickable to edit, ring when selected --}}
                        <template x-for="key in order" :key="key">
                            <div x-show="sections[key]" x-on:click="edit(key)"
                                class="relative cursor-pointer transition"
                                :class="editing === key ? 'ring-2 ring-inset ring-brand-500' : 'hover:ring-1 hover:ring-inset hover:ring-brand-200'">

                                <template x-if="key === 'hero'">
                                    <section class="px-6 py-10 text-center sm:px-10">
                                        <span class="inline-flex items-center gap-1 rounded-full border border-neutral-200 px-2.5 py-1 text-[11px] text-neutral-600"><span class="text-amber-500">★★★★★</span> 4.9 · 214 reviews</span>
                                        <h1 class="mt-4 text-2xl font-bold leading-tight text-neutral-900 sm:text-3xl" x-text="content.hero.headline"></h1>
                                        <p class="mt-2 text-sm font-medium" :style="{ color: accent }" x-text="content.hero.tagline"></p>
                                        <p class="mx-auto mt-3 max-w-md text-sm text-neutral-600" x-text="content.hero.desc"></p>
                                        <div class="mt-6 flex items-center justify-center gap-3">
                                            <button class="px-6 py-3 text-sm font-semibold text-white" :style="{ background: accent, borderRadius: btnRadius }" x-text="content.hero.btn"></button>
                                            <span class="text-lg font-bold text-neutral-900"><span x-text="content.hero.price"></span> <span class="text-sm text-neutral-400 line-through" x-text="content.hero.compare"></span></span>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'product'">
                                    <section class="border-t border-neutral-100 px-6 py-9 sm:px-10">
                                        <div class="mx-auto max-w-md overflow-hidden rounded-2xl border border-neutral-200 shadow-sm">
                                            <div class="flex items-center gap-3 border-b border-neutral-100 px-5 py-4">
                                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl text-white" :style="{ background: accent }"><x-heroicon-o-cube class="h-6 w-6" /></span>
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-bold text-neutral-900" x-text="content.product.name"></p>
                                                    <p class="text-[11px] uppercase tracking-wide text-neutral-400" x-text="content.product.type"></p>
                                                </div>
                                            </div>
                                            <div class="px-5 py-4">
                                                <p class="text-sm text-neutral-600" x-text="content.product.summary"></p>
                                                <div class="mt-4 flex items-baseline gap-2">
                                                    <span class="text-2xl font-bold text-neutral-900" x-text="content.product.price"></span>
                                                    <span class="text-sm text-neutral-400 line-through" x-show="content.product.compare" x-text="content.product.compare"></span>
                                                </div>
                                                <button class="mt-4 w-full py-3 text-sm font-semibold text-white" :style="{ background: accent, borderRadius: btnRadius }" x-text="content.product.btn"></button>
                                                <p class="mt-2 text-center text-[11px] text-neutral-400" x-text="content.product.note"></p>
                                            </div>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'stats'">
                                    <section class="border-t border-neutral-100 px-6 py-8 sm:px-10">
                                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                            <template x-for="(s, i) in content.stats" :key="i">
                                                <div class="text-center">
                                                    <p class="text-2xl font-extrabold text-neutral-900" :style="{ color: accent }" x-text="s.value"></p>
                                                    <p class="mt-0.5 text-[11px] uppercase tracking-wide text-neutral-400" x-text="s.label"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'logos'">
                                    <section class="border-t border-neutral-100 bg-neutral-50/60 px-6 py-7 sm:px-10">
                                        <p class="text-center text-[11px] font-semibold uppercase tracking-wider text-neutral-400">Trusted by teams at</p>
                                        <div class="mt-4 flex flex-wrap items-center justify-center gap-x-8 gap-y-3">
                                            <template x-for="(l, i) in content.logos" :key="i">
                                                <span class="text-sm font-bold text-neutral-400" x-text="l"></span>
                                            </template>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'features'">
                                    <section class="border-t border-neutral-100 bg-neutral-50/60 px-6 py-8 sm:px-10">
                                        <h2 class="text-center text-lg font-bold">Everything you need</h2>
                                        <div class="mt-5 grid grid-cols-2 gap-3">
                                            <template x-for="(f, i) in content.features" :key="i">
                                                <div class="rounded-xl border border-neutral-200 bg-white p-3">
                                                    <span class="grid h-8 w-8 place-items-center rounded-lg text-white" :style="{ background: accent }">★</span>
                                                    <p class="mt-2 text-xs font-semibold text-neutral-800" x-text="f"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'benefits'">
                                    <section class="px-6 py-8 sm:px-10">
                                        <h2 class="text-lg font-bold">Why buyers choose it</h2>
                                        <ul class="mt-4 space-y-2">
                                            <template x-for="(b, i) in content.benefits" :key="i">
                                                <li class="flex items-center gap-2 text-sm text-neutral-700"><span class="grid h-4 w-4 place-items-center rounded-full text-[9px] text-white" :style="{ background: accent }">✓</span><span x-text="b"></span></li>
                                            </template>
                                        </ul>
                                    </section>
                                </template>

                                <template x-if="key === 'steps'">
                                    <section class="border-t border-neutral-100 px-6 py-9 sm:px-10">
                                        <h2 class="text-center text-lg font-bold">How it works</h2>
                                        <div class="mt-6 grid gap-5 sm:grid-cols-3">
                                            <template x-for="(s, i) in content.steps" :key="i">
                                                <div class="text-center">
                                                    <span class="mx-auto grid h-9 w-9 place-items-center rounded-full text-sm font-bold text-white" :style="{ background: accent }" x-text="i + 1"></span>
                                                    <p class="mt-3 text-sm font-semibold text-neutral-900" x-text="s.title"></p>
                                                    <p class="mt-1 text-xs text-neutral-500" x-text="s.desc"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'bonuses'">
                                    <section class="border-t border-neutral-100 bg-neutral-50/60 px-6 py-9 sm:px-10">
                                        <h2 class="text-center text-lg font-bold">Everything included today</h2>
                                        <div class="mx-auto mt-5 max-w-md divide-y divide-neutral-100 rounded-xl border border-neutral-200 bg-white">
                                            <template x-for="(b, i) in content.bonuses" :key="i">
                                                <div class="flex items-center justify-between px-4 py-3 text-sm">
                                                    <span class="flex items-center gap-2 text-neutral-800"><span :style="{ color: accent }">✓</span><span x-text="b.title"></span></span>
                                                    <span class="font-semibold text-neutral-400 line-through" x-text="b.value"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'video'">
                                    <section class="border-t border-neutral-100 px-6 py-9 text-center sm:px-10">
                                        <h2 class="text-lg font-bold" x-text="content.video.title"></h2>
                                        <p class="mt-1 text-sm text-neutral-500" x-text="content.video.subtitle"></p>
                                        <div class="mx-auto mt-5 grid aspect-video max-w-lg place-items-center rounded-xl bg-neutral-900/90">
                                            <span class="grid h-14 w-14 place-items-center rounded-full bg-white/90 text-neutral-900 shadow-lg"><x-heroicon-s-play class="h-6 w-6" /></span>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'testimonials'">
                                    <section class="border-t border-neutral-100 bg-neutral-50/60 px-6 py-8 sm:px-10">
                                        <h2 class="text-center text-lg font-bold">Loved by buyers</h2>
                                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                            <template x-for="(t, i) in content.testimonials" :key="i">
                                                <figure class="rounded-xl border border-neutral-200 bg-white p-3">
                                                    <div class="text-amber-500 text-xs">★★★★★</div>
                                                    <blockquote class="mt-1 text-xs text-neutral-700" x-text="'“' + t.quote + '”'"></blockquote>
                                                    <figcaption class="mt-1.5 text-[11px] text-neutral-400"><span class="font-semibold text-neutral-600" x-text="t.name"></span><span x-show="t.role" x-text="' · ' + (t.role || '')"></span></figcaption>
                                                </figure>
                                            </template>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'faq'">
                                    <section class="px-6 py-8 sm:px-10">
                                        <h2 class="text-lg font-bold">Questions & answers</h2>
                                        <div class="mt-3 space-y-2">
                                            <template x-for="(item, i) in content.faq" :key="i">
                                                <div class="rounded-xl border border-neutral-200 px-4 py-3">
                                                    <p class="text-sm font-medium text-neutral-800" x-text="item.q"></p>
                                                    <p class="mt-1 text-xs text-neutral-500" x-text="item.a"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'guarantee'">
                                    <section class="px-6 py-6 sm:px-10">
                                        <div class="flex items-center gap-3 rounded-xl border p-4" :style="{ borderColor: accent, background: accent + '10' }">
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-white" :style="{ background: accent }">✓</span>
                                            <p class="text-sm font-medium text-neutral-800" x-text="content.guarantee"></p>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'countdown'">
                                    <section class="px-6 py-6 text-center sm:px-10">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500" x-text="content.countdown"></p>
                                        <div class="mt-2 flex justify-center gap-2">
                                            <template x-for="u in ['02','11','45']" :key="u"><span class="rounded-lg px-3 py-2 text-lg font-bold text-white" :style="{ background: accent }" x-text="u"></span></template>
                                        </div>
                                    </section>
                                </template>

                                <template x-if="key === 'cta'">
                                    <section class="border-t border-neutral-100 px-6 py-10 text-center sm:px-10">
                                        <h2 class="text-xl font-bold" x-text="content.cta.heading"></h2>
                                        <button class="mt-5 px-8 py-3.5 text-sm font-semibold text-white" :style="{ background: accent, borderRadius: btnRadius }" x-text="content.cta.btn"></button>
                                        <p class="mt-2 text-[11px] text-neutral-400">Secure checkout · wallet, card, crypto, bank & mobile money</p>
                                    </section>
                                </template>
                            </div>
                        </template>

                        <footer class="border-t border-neutral-100 py-4 text-center text-[11px] text-neutral-400">Powered by PoisaHub</footer>
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
