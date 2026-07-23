<x-layouts.marketing :title="__('FAQs')" :description="__('Answers to common questions about using PoisaPay — deposits, withdrawals, cards, security and more.')">
    <div class="mx-auto max-w-3xl px-4 pb-24 pt-6 sm:px-6">
        {{-- Hero --}}
        <div class="text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.18em]" style="color:var(--brand)">{{ __('Help center') }}</p>
            <h1 class="mt-3 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">
                {{ __('Questions,') }} <span class="grad-text">{{ __('answered.') }}</span>
            </h1>
            <p class="mx-auto mt-4 max-w-xl text-slate-600">
                {{ __('Everything you need to know about deposits, withdrawals, cards and security on PoisaPay.') }}
            </p>
        </div>

        {{-- FAQ groups --}}
        <div class="mt-14 space-y-12">
            @forelse ($groups as $group => $faqs)
                <section>
                    <h2 class="mb-4 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-900">
                        <span class="h-1.5 w-1.5 rounded-full" style="background:var(--brand)"></span>
                        {{ $group }}
                    </h2>
                    <div class="space-y-3" x-data="{ open: null }">
                        @foreach ($faqs as $faq)
                            @php $i = $loop->index; @endphp
                            <div class="glass glass-hover overflow-hidden rounded-2xl">
                                <button type="button"
                                    @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                                    class="flex w-full items-center justify-between gap-4 p-5 text-left"
                                    :aria-expanded="open === {{ $i }}">
                                    <span class="text-sm font-semibold text-slate-900 sm:text-base">{{ $faq->question }}</span>
                                    <span class="grid h-8 w-8 flex-none place-items-center rounded-full bg-white/70 text-slate-500 ring-1 ring-slate-200 transition-transform duration-300"
                                        :class="open === {{ $i }} ? 'rotate-180' : ''">
                                        <x-heroicon-o-chevron-down class="h-4 w-4" />
                                    </span>
                                </button>
                                <div x-show="open === {{ $i }}" x-cloak
                                    x-transition:enter="transition duration-300 ease-out"
                                    x-transition:enter-start="opacity-0 -translate-y-2"
                                    x-transition:enter-end="opacity-100 translate-y-0">
                                    <p class="border-t border-slate-200/70 px-5 pb-5 pt-4 text-sm leading-relaxed text-slate-600">{{ $faq->answer }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="glass rounded-2xl p-10 text-center text-slate-500">
                    {{ __('No FAQs published yet. Please check back soon.') }}
                </div>
            @endforelse
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
