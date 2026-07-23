<x-layouts.marketing :title="$product['title']" :description="$product['lead']">
    {{-- ═══════════ Hero ═══════════ --}}
    <section class="mx-auto max-w-6xl px-4 pt-14 sm:px-6 sm:pt-20">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            {{-- Copy --}}
            <div class="text-center lg:text-left">
                <span class="glass inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <x-dynamic-component :component="'heroicon-o-'.$product['icon']" class="h-3.5 w-3.5" style="color:var(--brand)" />
                    {{ $product['eyebrow'] }}
                </span>
                <h1 class="mt-5 text-4xl font-extrabold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl">
                    {{ $product['title'] }}
                </h1>
                <p class="mx-auto mt-4 max-w-md text-lg text-slate-600 lg:mx-0">{{ $product['lead'] }}</p>

                <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row lg:justify-start justify-center">
                    @auth
                        <a href="{{ route('dashboard') }}" class="pp-btn pp-btn-primary pp-btn-lg">{{ __('Go to dashboard') }}</a>
                    @else
                        <a href="{{ route('register') }}" class="pp-btn pp-btn-primary pp-btn-lg">{{ __('Get started free') }}</a>
                        <a href="{{ route('login') }}" class="pp-btn pp-btn-ghost pp-btn-lg">{{ __('Log in') }}</a>
                    @endauth
                </div>

                <p class="mt-5 inline-flex items-center gap-1.5 text-xs font-medium text-slate-400">
                    <x-heroicon-o-shield-check class="h-4 w-4" style="color:var(--brand)" />
                    {{ __('Bank-grade security · KYC & AML protected') }}
                </p>
            </div>

            {{-- Visual --}}
            <div class="relative flex justify-center lg:justify-end">
                <div class="pointer-events-none absolute -right-6 -top-8 h-56 w-56 rounded-full blur-3xl" style="background:color-mix(in srgb,var(--brand) 22%,transparent)"></div>
                <div class="pointer-events-none absolute -bottom-10 left-0 h-40 w-40 rounded-full blur-3xl" style="background:color-mix(in srgb,var(--indigo,#6366f1) 18%,transparent)"></div>

                <div class="relative w-full max-w-sm">
                    @switch($slug)
                        @case('virtual-card')
                            {{-- Card mockup --}}
                            <div class="relative aspect-[1.586/1] overflow-hidden rounded-3xl p-6 text-white shadow-2xl shadow-blue-900/30"
                                style="background:linear-gradient(135deg,var(--brand),var(--brand-600) 60%,#0b3aa8)">
                                <div class="absolute inset-0 opacity-25" style="background-image:radial-gradient(circle at 82% 12%,#fff 1px,transparent 1px);background-size:26px 26px"></div>
                                <div class="relative flex h-full flex-col justify-between">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-semibold">PoisaPay</span>
                                        <span class="rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">{{ __('Virtual') }}</span>
                                    </div>
                                    <div class="h-8 w-11 rounded-md bg-gradient-to-br from-yellow-100 to-amber-300 shadow-inner"></div>
                                    <div>
                                        <p class="font-mono text-xl tracking-widest">•••• •••• •••• 4291</p>
                                        <div class="mt-3 flex items-end justify-between">
                                            <div>
                                                <p class="text-[9px] uppercase tracking-wider text-white/60">{{ __('Card holder') }}</p>
                                                <p class="text-xs font-medium uppercase tracking-wide">A. RAHMAN</p>
                                            </div>
                                            <span class="text-lg font-bold italic">VISA</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @break

                        @case('wallet')
                            {{-- Balance panel --}}
                            <div class="glass-card p-6">
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ __('Total balance') }}</p>
                                <p class="mt-1 text-3xl font-bold tracking-tight text-slate-900">$12,480.50</p>
                                <div class="mt-5 space-y-3">
                                    @foreach ([['USDT','Tether','68','bg-emerald-500'],['ETH','Ethereum','21','bg-indigo-500'],['BTC','Bitcoin','11','bg-orange-500']] as $row)
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-[10px] font-bold text-white {{ $row[3] }}">{{ $row[0] }}</span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm font-semibold text-slate-900">{{ $row[0] }}</span>
                                                    <span class="text-xs text-slate-400">{{ $row[2] }}%</span>
                                                </div>
                                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                                    <div class="h-full rounded-full {{ $row[3] }}" style="width: {{ $row[2] }}%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @break

                        @case('exchange')
                            {{-- Swap widget --}}
                            <div class="glass-card p-6">
                                <p class="text-sm font-semibold text-slate-900">{{ __('Swap') }}</p>
                                <div class="relative mt-4 space-y-2">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                        <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('You pay') }}</p>
                                        <div class="mt-1 flex items-center justify-between">
                                            <span class="text-2xl font-bold text-slate-900">100.00</span>
                                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">USDT</span>
                                        </div>
                                    </div>
                                    <div class="absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-1/2">
                                        <span class="grid h-9 w-9 place-items-center rounded-full text-white shadow-lg" style="background:var(--brand)">
                                            <x-heroicon-o-arrows-up-down class="h-4 w-4" />
                                        </span>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                        <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('You get') }}</p>
                                        <div class="mt-1 flex items-center justify-between">
                                            <span class="text-2xl font-bold text-slate-900">11,940</span>
                                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">BDT</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-3 text-center text-xs text-slate-400">{{ __('Rate 1 USDT ≈ 119.40 BDT · spread shown up front') }}</p>
                            </div>
                            @break

                        @case('merchant-pay')
                            {{-- Invoice / QR --}}
                            <div class="glass-card p-6 text-center">
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ __('Invoice · INV-1042') }}</p>
                                <p class="mt-1 text-3xl font-bold tracking-tight text-slate-900">50.00 <span class="text-base font-semibold text-slate-400">USDT</span></p>
                                <div class="mx-auto mt-5 grid h-40 w-40 place-items-center rounded-2xl border border-slate-200 bg-white text-slate-900">
                                    <x-heroicon-o-qr-code class="h-28 w-28" />
                                </div>
                                <p class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> {{ __('Awaiting payment') }}
                                </p>
                            </div>
                            @break
                    @endswitch
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════ Stats ═══════════ --}}
    @if (! empty($product['stats']))
        <section class="mx-auto mt-16 max-w-6xl px-4 sm:px-6">
            <div class="grid grid-cols-2 gap-6 rounded-3xl border border-slate-200 bg-white px-6 py-8 shadow-sm sm:grid-cols-4">
                @foreach ($product['stats'] as $s)
                    <div class="text-center">
                        <p class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ $s['value'] }}</p>
                        <p class="mt-1 text-xs font-medium text-slate-500">{{ $s['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ═══════════ Features ═══════════ --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-24">
        <div class="mx-auto max-w-xl text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.16em]" style="color:var(--brand)">{{ __('Features') }}</p>
            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('Everything you need') }}</h2>
        </div>
        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($product['features'] as $f)
                <div class="glass glass-hover flex gap-4 rounded-2xl p-6">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-white shadow-sm" style="background:linear-gradient(135deg,var(--brand),var(--brand-600))">
                        <x-dynamic-component :component="'heroicon-o-'.$f['icon']" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-900">{{ $f['title'] }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ $f['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ═══════════ How it works ═══════════ --}}
    @if (! empty($product['steps']))
        <section class="mx-auto max-w-5xl px-4 pb-8 sm:px-6">
            <div class="mx-auto max-w-xl text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.16em]" style="color:var(--brand)">{{ __('How it works') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('Up and running in minutes') }}</h2>
            </div>
            <div class="mt-12 grid gap-8 sm:grid-cols-3">
                @foreach ($product['steps'] as $i => $step)
                    <div class="text-center sm:text-left">
                        <span class="mx-auto grid h-11 w-11 place-items-center rounded-xl text-base font-bold text-white shadow-sm sm:mx-0" style="background:linear-gradient(135deg,var(--brand),var(--brand-600))">{{ $i + 1 }}</span>
                        <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $step['title'] }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ $step['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ═══════════ FAQ ═══════════ --}}
    @if (! empty($product['faqs']))
        <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6" x-data="{ open: 0 }">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('Frequently asked questions') }}</h2>
            </div>
            <div class="mt-10 space-y-3">
                @foreach ($product['faqs'] as $i => $faq)
                    <div class="overflow-hidden rounded-2xl border bg-white transition"
                        :class="open === {{ $i }} ? 'border-blue-500/40 shadow-md ring-1 ring-blue-500/20' : 'border-slate-200'">
                        <button type="button" @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                            class="flex w-full items-center justify-between gap-4 p-5 text-left" :aria-expanded="open === {{ $i }}">
                            <span class="text-sm font-semibold text-slate-900 sm:text-base">{{ $faq['q'] }}</span>
                            <span class="grid h-8 w-8 flex-none place-items-center rounded-full ring-1 transition-all duration-300"
                                :class="open === {{ $i }} ? 'rotate-180 bg-blue-600 text-white ring-blue-600' : 'bg-slate-50 text-slate-500 ring-slate-200'">
                                <x-heroicon-o-chevron-down class="h-4 w-4" />
                            </span>
                        </button>
                        <div x-show="open === {{ $i }}" x-cloak
                            x-transition:enter="transition duration-300 ease-out"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0">
                            <p class="border-t border-slate-100 px-5 pb-5 pt-4 text-sm leading-relaxed text-slate-600">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.marketing>
