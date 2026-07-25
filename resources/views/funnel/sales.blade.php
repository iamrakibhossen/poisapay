<x-layouts.sales :title="$product['name']">
<div x-data="{
        checkout: false,
        bump: false,
        base: 49, bumpPrice: 19,
        get total() { return this.base + (this.bump ? this.bumpPrice : 0); },
    }">

    {{-- Sticky top bar --}}
    <header class="sticky top-0 z-30 border-b border-neutral-200/70 bg-white/85 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6">
            <div class="flex items-center gap-2.5">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-s-bolt class="h-4 w-4" /></span>
                <span class="text-sm font-bold">{{ $product['seller']['name'] }}</span>
            </div>
            <button x-on:click="checkout = true" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                {{ __('Get it for') }} {{ $product['price'] }}
            </button>
        </div>
    </header>

    <main>
        {{-- Hero --}}
        <section class="relative overflow-hidden">
            <div class="absolute -right-16 -top-24 h-72 w-72 rounded-full bg-brand-300/20 blur-3xl"></div>
            <div class="mx-auto grid max-w-5xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:py-20">
                <div class="relative">
                    <div class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-3 py-1 text-xs font-medium text-neutral-600">
                        <span class="flex items-center gap-1 text-amber-500">★★★★★</span>
                        {{ $product['rating'] }} · {{ number_format($product['reviewsCount']) }} {{ __('reviews') }} · {{ $product['salesCount'] }} {{ __('sold') }}
                    </div>
                    <h1 class="mt-4 text-4xl font-bold leading-tight tracking-tight text-neutral-900 sm:text-5xl">{{ $product['name'] }}</h1>
                    <p class="mt-3 text-lg font-medium text-brand-700">{{ $product['tagline'] }}</p>
                    <p class="mt-4 text-base leading-relaxed text-neutral-600">{{ $product['summary'] }}</p>
                    <div class="mt-7 flex flex-wrap items-center gap-4">
                        <button x-on:click="checkout = true" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700">
                            <x-heroicon-o-lock-closed class="h-5 w-5" /> {{ __('Buy now') }}
                        </button>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-neutral-900">{{ $product['price'] }}</span>
                            <span class="text-sm text-neutral-400 line-through">{{ $product['comparePrice'] }}</span>
                        </div>
                    </div>
                    <p class="mt-3 flex items-center gap-1.5 text-xs text-neutral-500"><x-heroicon-o-shield-check class="h-4 w-4 text-emerald-500" /> {{ __('14-day money-back guarantee · instant download') }}</p>
                </div>
                {{-- Product visual --}}
                <div class="relative">
                    <div class="aspect-[4/3] rounded-2xl border border-neutral-200 bg-gradient-to-br from-brand-50 to-white shadow-xl shadow-neutral-900/5">
                        <div class="grid h-full place-items-center">
                            <div class="text-center">
                                <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-brand-500 text-white shadow-lg"><x-heroicon-o-cube class="h-8 w-8" /></span>
                                <p class="mt-3 text-sm font-semibold text-neutral-700">{{ $product['name'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Features --}}
        <section class="border-t border-neutral-100 bg-neutral-50/60">
            <div class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
                <h2 class="text-center text-2xl font-bold tracking-tight">{{ __('Everything you need to ship') }}</h2>
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($product['features'] as [$icon, $title, $desc])
                        <div class="rounded-2xl border border-neutral-200 bg-white p-5">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" /></span>
                            <p class="mt-3 text-sm font-semibold text-neutral-900">{{ $title }}</p>
                            <p class="mt-1 text-xs leading-relaxed text-neutral-500">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Benefits --}}
        <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight">{{ __('Why creators choose it') }}</h2>
            <ul class="mt-6 space-y-3">
                @foreach ($product['benefits'] as $b)
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600"><x-heroicon-s-check class="h-3.5 w-3.5" /></span>
                        <span class="text-sm text-neutral-700">{{ $b }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Testimonials --}}
        <section class="border-t border-neutral-100 bg-neutral-50/60">
            <div class="mx-auto max-w-5xl px-4 py-14 sm:px-6">
                <h2 class="text-center text-2xl font-bold tracking-tight">{{ __('Loved by builders') }}</h2>
                <div class="mt-8 grid gap-4 sm:grid-cols-3">
                    @foreach ($product['testimonials'] as [$name, $role, $quote])
                        <figure class="rounded-2xl border border-neutral-200 bg-white p-5">
                            <div class="text-amber-500">★★★★★</div>
                            <blockquote class="mt-2 text-sm leading-relaxed text-neutral-700">“{{ $quote }}”</blockquote>
                            <figcaption class="mt-3 text-xs font-medium text-neutral-500">{{ $name }} · {{ $role }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- FAQ --}}
        <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight">{{ __('Questions & answers') }}</h2>
            <div class="mt-6 divide-y divide-neutral-100 rounded-2xl border border-neutral-200 bg-white">
                @foreach ($product['faq'] as [$q, $a])
                    <div x-data="{ open: false }" class="p-5">
                        <button x-on:click="open = ! open" class="flex w-full items-center justify-between gap-4 text-left">
                            <span class="text-sm font-semibold text-neutral-900">{{ $q }}</span>
                            <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 text-neutral-400 transition" x-bind:class="open && 'rotate-180'" />
                        </button>
                        <p x-show="open" x-transition x-cloak class="mt-2 text-sm leading-relaxed text-neutral-600">{{ $a }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Final CTA --}}
        <section class="border-t border-neutral-100">
            <div class="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6">
                <h2 class="text-3xl font-bold tracking-tight">{{ __('Start building today') }}</h2>
                <p class="mx-auto mt-3 max-w-xl text-neutral-600">{{ $product['summary'] }}</p>
                <button x-on:click="checkout = true" class="mt-7 inline-flex items-center gap-2 rounded-xl bg-brand-600 px-8 py-4 text-base font-semibold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700">
                    {{ __('Get it for') }} {{ $product['price'] }} <x-heroicon-o-arrow-right class="h-5 w-5" />
                </button>
                <p class="mt-3 text-xs text-neutral-500">{{ __('Secure checkout · powered by PoisaPay') }}</p>
            </div>
        </section>

        <footer class="border-t border-neutral-100 py-8 text-center text-xs text-neutral-400">
            {{ __('Powered by') }} <span class="font-semibold text-neutral-500">PoisaHub</span>
        </footer>
    </main>

    {{-- ===================== Checkout drawer (slide-over) ===================== --}}
    <div x-show="checkout" x-cloak class="fixed inset-0 z-50">
        <div x-show="checkout" x-transition.opacity class="absolute inset-0 bg-neutral-900/40" x-on:click="checkout = false"></div>
        <div x-show="checkout"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
            class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                <h3 class="text-base font-semibold">{{ __('Checkout') }}</h3>
                <button x-on:click="checkout = false" class="rounded-full p-1.5 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
            </div>

            <form method="POST" action="{{ route('funnel.checkout', ['slug' => $product['slug']]) }}" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                    {{-- Line item --}}
                    <div class="flex items-center gap-3 rounded-xl border border-neutral-200 p-3">
                        <span class="grid h-11 w-11 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-o-cube class="h-5 w-5" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-neutral-900">{{ $product['name'] }}</p>
                            <p class="text-xs text-neutral-500">{{ __('Digital download') }}</p>
                        </div>
                        <span class="text-sm font-semibold">{{ $product['price'] }}</span>
                    </div>

                    {{-- Contact --}}
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Email') }}</label>
                        <input type="email" name="email" required placeholder="you@example.com"
                            class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                    </div>

                    {{-- Order bump --}}
                    <label class="flex cursor-pointer gap-3 rounded-xl border-2 p-3 transition"
                        x-bind:class="bump ? 'border-brand-500 bg-brand-50/50' : 'border-dashed border-neutral-300'">
                        <input type="checkbox" x-model="bump" name="bump" class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-brand-600" />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-neutral-900">{{ __('Yes, add') }} {{ $product['bump']['name'] }}</span>
                                <span class="text-sm font-semibold text-brand-700">+{{ $product['bump']['price'] }}</span>
                            </span>
                            <span class="mt-0.5 block text-xs text-neutral-500">{{ $product['bump']['desc'] }}</span>
                        </span>
                    </label>

                    {{-- Coupon --}}
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Coupon (optional)') }}</label>
                        <div class="flex gap-2">
                            <input type="text" name="coupon" placeholder="CODE"
                                class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm uppercase focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                            <button type="button" class="rounded-lg border border-neutral-200 px-4 text-sm font-medium text-neutral-600 hover:bg-neutral-50">{{ __('Apply') }}</button>
                        </div>
                    </div>

                    {{-- Payment — PoisaPay only --}}
                    <div>
                        <p class="mb-2 text-xs font-medium text-neutral-500">{{ __('Payment') }}</p>
                        <input type="hidden" name="method" value="poisapay" />
                        <div class="flex items-center gap-3 rounded-xl border-2 border-brand-500 bg-brand-50/40 p-3.5">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-s-bolt class="h-5 w-5" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-neutral-900">{{ __('Pay with PoisaPay') }}</p>
                                <p class="text-xs text-neutral-500">{{ __('Wallet balance, card, crypto, bank & mobile money — all in one secure step.') }}</p>
                            </div>
                            <x-heroicon-s-check-circle class="h-5 w-5 shrink-0 text-brand-600" />
                        </div>
                        <p class="mt-2 flex items-center gap-1 text-[11px] text-neutral-400">
                            <x-heroicon-o-lock-closed class="h-3 w-3" /> {{ __('You confirm securely in PoisaPay. New here? Create a wallet in seconds.') }}
                        </p>
                    </div>
                </div>

                {{-- Footer / total --}}
                <div class="border-t border-neutral-100 px-5 py-4">
                    <div class="mb-3 flex items-center justify-between text-sm">
                        <span class="text-neutral-500">{{ __('Total') }}</span>
                        <span class="text-lg font-bold text-neutral-900">$<span x-text="total"></span></span>
                    </div>
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-700">
                        <x-heroicon-o-lock-closed class="h-4 w-4" /> {{ __('Pay') }} $<span x-text="total"></span>
                    </button>
                    <p class="mt-2 text-center text-[11px] text-neutral-400">{{ __('Secured by PoisaPay · 14-day guarantee') }}</p>
                </div>
            </form>
        </div>
    </div>
</div>
</x-layouts.sales>
