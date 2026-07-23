<x-layouts.marketing :title="__('FAQs')" :description="__('Answers to common questions about using PoisaPay — deposits, withdrawals, cards, security and more.')">
    @php
        // Flattened lowercased text of every FAQ, for the client-side search "no results" check.
        $allText = collect($groups)->flatMap(fn ($faqs) => $faqs->map(fn ($f) => \Illuminate\Support\Str::lower($f->question.' '.$f->answer)))->values();
    @endphp

    <div class="mx-auto max-w-5xl px-4 pb-24 pt-6 sm:px-6" x-data="{ q: '', open: null }">
        {{-- Hero + search --}}
        <div class="text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.18em]" style="color:var(--brand)">{{ __('Help center') }}</p>
            <h1 class="mt-3 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">
                {{ __('Questions,') }} <span class="grad-text">{{ __('answered.') }}</span>
            </h1>
            <p class="mx-auto mt-4 max-w-xl text-slate-600">
                {{ __('Everything you need to know about deposits, withdrawals, cards and security on PoisaPay.') }}
            </p>

            {{-- Search --}}
            <div class="relative mx-auto mt-8 max-w-xl">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input type="text" x-model="q" placeholder="{{ __('Search questions…') }}"
                    class="h-14 w-full rounded-2xl border border-slate-200 bg-white pl-12 pr-11 text-sm text-slate-900 shadow-sm placeholder-slate-400 transition focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500" />
                <button type="button" x-show="q" x-cloak @click="q = ''" aria-label="{{ __('Clear search') }}"
                    class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
            </div>
        </div>

        {{-- Two-column: category nav + FAQ groups --}}
        <div class="mt-12 lg:grid lg:grid-cols-[200px_1fr] lg:gap-12">
            {{-- Category sidebar (desktop) --}}
            <aside class="hidden lg:block">
                <div class="sticky top-24">
                    <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Categories') }}</p>
                    <nav class="space-y-1">
                        @foreach ($groups as $group => $faqs)
                            <a href="#{{ \Illuminate\Support\Str::slug($group) }}"
                                class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                                <span class="h-1.5 w-1.5 rounded-full" style="background:var(--brand)"></span>
                                {{ $group }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            </aside>

            {{-- FAQ content --}}
            <div class="space-y-10">
                {{-- Category chips (mobile) --}}
                @if ($groups->count() > 1)
                    <div class="-mx-1 flex flex-nowrap gap-2 overflow-x-auto px-1 pb-1 lg:hidden">
                        @foreach ($groups as $group => $faqs)
                            <a href="#{{ \Illuminate\Support\Str::slug($group) }}"
                                class="shrink-0 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-medium text-slate-600 transition hover:border-blue-300 hover:text-slate-900">
                                {{ $group }}
                            </a>
                        @endforeach
                    </div>
                @endif

                @forelse ($groups as $group => $faqs)
                    <section id="{{ \Illuminate\Support\Str::slug($group) }}" class="scroll-mt-24"
                        x-data="{ items: {{ \Illuminate\Support\Js::from($faqs->map(fn ($f) => \Illuminate\Support\Str::lower($f->question.' '.$f->answer))->values()) }} }"
                        x-show="!q.trim() || items.some(t => t.includes(q.toLowerCase().trim()))">
                        <h2 class="mb-4 text-sm font-bold uppercase tracking-[0.14em] text-slate-900">{{ $group }}</h2>
                        <div class="space-y-3">
                            @foreach ($faqs as $faq)
                                @php $key = \Illuminate\Support\Str::slug($group).'-'.$loop->index; @endphp
                                <div x-show="!q.trim() || {{ \Illuminate\Support\Js::from(\Illuminate\Support\Str::lower($faq->question.' '.$faq->answer)) }}.includes(q.toLowerCase().trim())"
                                    class="overflow-hidden rounded-2xl border bg-white transition duration-200"
                                    :class="open === '{{ $key }}' ? 'border-blue-500/40 shadow-md ring-1 ring-blue-500/20' : 'border-slate-200 hover:border-slate-300'">
                                    <button type="button"
                                        @click="open === '{{ $key }}' ? open = null : open = '{{ $key }}'"
                                        class="flex w-full items-center justify-between gap-4 p-5 text-left"
                                        :aria-expanded="open === '{{ $key }}'">
                                        <span class="text-sm font-semibold text-slate-900 sm:text-base">{{ $faq->question }}</span>
                                        <span class="grid h-8 w-8 flex-none place-items-center rounded-full ring-1 transition-all duration-300"
                                            :class="open === '{{ $key }}' ? 'rotate-180 bg-blue-600 text-white ring-blue-600' : 'bg-slate-50 text-slate-500 ring-slate-200'">
                                            <x-heroicon-o-chevron-down class="h-4 w-4" />
                                        </span>
                                    </button>
                                    <div x-show="open === '{{ $key }}'" x-cloak
                                        x-transition:enter="transition duration-300 ease-out"
                                        x-transition:enter-start="opacity-0 -translate-y-2"
                                        x-transition:enter-end="opacity-100 translate-y-0">
                                        <p class="border-t border-slate-100 px-5 pb-5 pt-4 text-sm leading-relaxed text-slate-600">{{ $faq->answer }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">
                        {{ __('No FAQs published yet. Please check back soon.') }}
                    </div>
                @endforelse

                {{-- No search results --}}
                <div x-show="q.trim() && ! {{ \Illuminate\Support\Js::from($allText) }}.some(t => t.includes(q.toLowerCase().trim()))" x-cloak
                    class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-10 text-center">
                    <x-heroicon-o-magnifying-glass class="mx-auto h-8 w-8 text-slate-300" />
                    <p class="mt-3 text-sm font-medium text-slate-700">{{ __('No results found') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Try a different search, or contact support below.') }}</p>
                </div>
            </div>
        </div>

        {{-- Contact CTA --}}
        <div class="glass-card mt-16 flex flex-col items-center gap-5 p-8 text-center sm:flex-row sm:justify-between sm:text-left">
            <div>
                <p class="text-lg font-bold text-slate-900">{{ __('Still have questions?') }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Our support team is here to help, around the clock.') }}</p>
            </div>
            <a href="{{ route('home') }}" class="pp-btn pp-btn-primary pp-btn-lg shrink-0">
                {{ __('Contact support') }} <x-heroicon-o-arrow-right class="h-5 w-5" />
            </a>
        </div>
    </div>
</x-layouts.marketing>
