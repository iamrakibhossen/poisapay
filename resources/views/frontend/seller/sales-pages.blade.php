<x-layouts.app :title="__('Sales page')">
    <div class="mt-6"
        x-data="{
            device: 'desktop',
            accent: '#2563eb',
            btn: 'rounded',
            font: 'Inter',
            editing: null,
            sections: { hero: true, features: true, benefits: true, testimonials: true, faq: true, guarantee: true, countdown: false, cta: true },
            order: ['hero', 'features', 'benefits', 'testimonials', 'faq', 'guarantee', 'countdown', 'cta'],
            labels: { hero: 'Hero', features: 'Features', benefits: 'Benefits', testimonials: 'Testimonials', faq: 'FAQ', guarantee: 'Guarantee', countdown: 'Countdown', cta: 'Final CTA' },
            content: {
                hero: { headline: 'LaunchKit — Laravel SaaS Boilerplate', tagline: 'Ship your SaaS in a weekend, not a quarter.', desc: 'A production-ready Laravel starter with auth, billing, teams and a beautiful admin.', btn: 'Buy now', price: '$49', compare: '$99' },
                features: ['Auth & teams', 'Billing built in', 'Beautiful UI', 'DX first'],
                benefits: ['Save 100+ hours of setup', 'Clean, tested codebase', 'Lifetime updates', '6 months of support'],
                testimonials: [{ name: 'Aisha K.', quote: 'I launched my MVP in 4 days.' }, { name: 'Tanvir H.', quote: 'Huge time saver for every project.' }],
                faq: ['What do I get?', 'Which license?', 'Refund policy?'],
                guarantee: '14-day money-back guarantee — buy with confidence.',
                countdown: 'Offer ends in',
                cta: { heading: 'Start building today', btn: 'Get it for $49 →' },
            },
            get btnRadius() { return this.btn === 'pill' ? '9999px' : this.btn === 'square' ? '2px' : '12px'; },
            move(i, d) { const j = i + d; if (j < 0 || j >= this.order.length) return; [this.order[i], this.order[j]] = [this.order[j], this.order[i]]; },
            edit(key) { this.sections[key] = true; this.editing = key; },
        }"
        :style="{ '--accent': accent }">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('seller.sales-pages') }}" class="inline-flex items-center gap-1 text-xs font-medium text-neutral-500 transition hover:text-neutral-900">
                    <x-heroicon-o-chevron-left class="h-3.5 w-3.5" /> {{ __('Sales pages') }}
                </a>
                <h1 class="mt-1 text-xl font-semibold tracking-tight text-neutral-900">{{ __('Sales page') }}</h1>
                <p class="text-xs text-neutral-500">{{ $product }} · <span class="font-mono">/p/{{ $slug }}</span></p>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex rounded-lg border border-neutral-200 p-0.5">
                    <button type="button" x-on:click="device = 'desktop'" :class="device === 'desktop' ? 'bg-neutral-900 text-white' : 'text-neutral-500'" class="grid h-7 w-8 place-items-center rounded-md transition"><x-heroicon-o-computer-desktop class="h-4 w-4" /></button>
                    <button type="button" x-on:click="device = 'mobile'" :class="device === 'mobile' ? 'bg-neutral-900 text-white' : 'text-neutral-500'" class="grid h-7 w-8 place-items-center rounded-md transition"><x-heroicon-o-device-phone-mobile class="h-4 w-4" /></button>
                </div>
                <x-ui.button href="{{ route('funnel.sales', ['slug' => $slug]) }}" target="_blank" variant="secondary" size="sm" icon="arrow-top-right-on-square">{{ __('View live') }}</x-ui.button>
                <x-ui.button size="sm" icon="check">{{ __('Publish') }}</x-ui.button>
            </div>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-[340px_1fr]">
            {{-- ============ Left: controls ============ --}}
            <div class="space-y-4">

                {{-- ---- Sections list (when nothing is being edited) ---- --}}
                <div x-show="editing === null">
                    <x-ui.card>
                        <p class="mb-1 text-sm font-semibold text-neutral-900">{{ __('Sections') }}</p>
                        <p class="mb-3 text-xs text-neutral-500">{{ __('Toggle, reorder, or click a section to edit its content.') }}</p>
                        <div class="space-y-1.5">
                            <template x-for="(key, i) in order" :key="key">
                                <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-2 py-1.5">
                                    <div class="flex flex-col">
                                        <button type="button" x-on:click="move(i, -1)" :disabled="i === 0" class="text-neutral-300 transition hover:text-neutral-600 disabled:opacity-30"><x-heroicon-o-chevron-up class="h-3 w-3" /></button>
                                        <button type="button" x-on:click="move(i, 1)" :disabled="i === order.length - 1" class="text-neutral-300 transition hover:text-neutral-600 disabled:opacity-30"><x-heroicon-o-chevron-down class="h-3 w-3" /></button>
                                    </div>
                                    <button type="button" x-on:click="edit(key)" class="flex-1 text-left text-sm text-neutral-700 hover:text-brand-700" x-text="labels[key]"></button>
                                    <button type="button" x-on:click="edit(key)" class="rounded-md p-1 text-neutral-300 transition hover:bg-neutral-100 hover:text-neutral-600"><x-heroicon-o-pencil-square class="h-3.5 w-3.5" /></button>
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input type="checkbox" x-model="sections[key]" class="peer sr-only" />
                                        <span class="h-5 w-9 rounded-full bg-neutral-200 transition peer-checked:bg-brand-500"></span>
                                        <span class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-4"></span>
                                    </label>
                                </div>
                            </template>
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

                        @php $lbl = 'mb-1 block text-xs font-medium text-neutral-500'; $inp = 'w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500'; @endphp

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

                        {{-- FEATURES --}}
                        <div x-show="editing === 'features'" class="space-y-2">
                            <template x-for="(f, i) in content.features" :key="i">
                                <input class="{{ $inp }}" x-model="content.features[i]" />
                            </template>
                        </div>

                        {{-- BENEFITS --}}
                        <div x-show="editing === 'benefits'" class="space-y-2">
                            <template x-for="(b, i) in content.benefits" :key="i">
                                <input class="{{ $inp }}" x-model="content.benefits[i]" />
                            </template>
                        </div>

                        {{-- TESTIMONIALS --}}
                        <div x-show="editing === 'testimonials'" class="space-y-3">
                            <template x-for="(t, i) in content.testimonials" :key="i">
                                <div class="rounded-lg border border-neutral-200 p-2.5 space-y-2">
                                    <input class="{{ $inp }}" x-model="content.testimonials[i].name" placeholder="Name" />
                                    <textarea rows="2" class="{{ $inp }}" x-model="content.testimonials[i].quote" placeholder="Quote"></textarea>
                                </div>
                            </template>
                        </div>

                        {{-- FAQ --}}
                        <div x-show="editing === 'faq'" class="space-y-2">
                            <template x-for="(q, i) in content.faq" :key="i">
                                <input class="{{ $inp }}" x-model="content.faq[i]" />
                            </template>
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
                            <span class="text-sm font-bold">Rahim Studios</span>
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
                                <template x-if="key === 'features'">
                                    <section class="border-t border-neutral-100 bg-neutral-50/60 px-6 py-8 sm:px-10">
                                        <h2 class="text-center text-lg font-bold">Everything you need to ship</h2>
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
                                        <h2 class="text-lg font-bold">Why creators choose it</h2>
                                        <ul class="mt-4 space-y-2">
                                            <template x-for="(b, i) in content.benefits" :key="i">
                                                <li class="flex items-center gap-2 text-sm text-neutral-700"><span class="grid h-4 w-4 place-items-center rounded-full text-[9px] text-white" :style="{ background: accent }">✓</span><span x-text="b"></span></li>
                                            </template>
                                        </ul>
                                    </section>
                                </template>
                                <template x-if="key === 'testimonials'">
                                    <section class="border-t border-neutral-100 bg-neutral-50/60 px-6 py-8 sm:px-10">
                                        <h2 class="text-center text-lg font-bold">Loved by builders</h2>
                                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                            <template x-for="(t, i) in content.testimonials" :key="i">
                                                <figure class="rounded-xl border border-neutral-200 bg-white p-3">
                                                    <div class="text-amber-500 text-xs">★★★★★</div>
                                                    <blockquote class="mt-1 text-xs text-neutral-700" x-text="'“' + t.quote + '”'"></blockquote>
                                                    <figcaption class="mt-1.5 text-[11px] text-neutral-400" x-text="t.name"></figcaption>
                                                </figure>
                                            </template>
                                        </div>
                                    </section>
                                </template>
                                <template x-if="key === 'faq'">
                                    <section class="px-6 py-8 sm:px-10">
                                        <h2 class="text-lg font-bold">Questions & answers</h2>
                                        <div class="mt-3 divide-y divide-neutral-100 rounded-xl border border-neutral-200">
                                            <template x-for="(q, i) in content.faq" :key="i">
                                                <div class="flex items-center justify-between px-4 py-3 text-sm"><span class="font-medium text-neutral-800" x-text="q"></span><span class="text-neutral-300">+</span></div>
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
    </div>
</x-layouts.app>
