<x-landing::layouts.master :seo="$seo">
    {{-- ═══════════ Hero ═══════════ --}}
    <section class="mx-auto max-w-7xl px-4 pt-14 sm:px-6 lg:px-8 sm:pt-20">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            {{-- Copy --}}
            <div class="text-center lg:text-left">
                <span class="lp-glass inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-600">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-60" style="background:var(--brand)"></span>
                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full" style="background:var(--brand)"></span>
                    </span>
                    {{ $product['eyebrow'] }}
                </span>
                <h1 class="mt-5 text-[2.6rem] font-extrabold leading-[1.05] tracking-tight text-slate-900 sm:text-6xl">
                    {{ $product['title'] }}
                </h1>
                <p class="mx-auto mt-5 max-w-lg text-lg leading-relaxed text-slate-600 lg:mx-0">{{ $product['lead'] }}</p>

                <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row lg:justify-start justify-center">
                    @if (! empty($product['cta']))
                        {{-- Product-specific primary CTA (e.g. "Start selling"). --}}
                        <a href="{{ route($product['cta']['route']) }}" class="lp-btn lp-btn-primary lp-btn-lg">
                            {{ __($product['cta']['label']) }} <x-heroicon-o-arrow-right class="h-5 w-5" />
                        </a>
                        @if (! empty($product['pricing']))
                            <a href="#pricing" class="lp-btn lp-btn-ghost lp-btn-lg">{{ __('See plans') }}</a>
                        @else
                            @guest
                                <a href="{{ route('login') }}" class="lp-btn lp-btn-ghost lp-btn-lg">{{ __('Log in') }}</a>
                            @endguest
                        @endif
                    @else
                        @auth
                            <a href="{{ route('dashboard') }}" class="lp-btn lp-btn-primary lp-btn-lg">{{ __('Go to dashboard') }} <x-heroicon-o-arrow-right class="h-5 w-5" /></a>
                        @else
                            <a href="{{ route('register') }}" class="lp-btn lp-btn-primary lp-btn-lg">{{ __('Get started free') }} <x-heroicon-o-arrow-right class="h-5 w-5" /></a>
                            <a href="{{ route('login') }}" class="lp-btn lp-btn-ghost lp-btn-lg">{{ __('Log in') }}</a>
                        @endauth
                    @endif
                </div>

                @if (! empty($product['highlights']))
                    <ul class="mt-6 flex flex-wrap justify-center gap-x-5 gap-y-2 lg:justify-start">
                        @foreach ($product['highlights'] as $h)
                            <li class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600">
                                <x-heroicon-s-check-circle class="h-4 w-4 flex-none" style="color:var(--brand)" />
                                {{ __($h) }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-5 inline-flex items-center gap-1.5 text-xs font-medium text-slate-400">
                        <x-heroicon-o-shield-check class="h-4 w-4" style="color:var(--brand)" />
                        {{ __('Bank-grade security · KYC & AML protected') }}
                    </p>
                @endif

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
                                        <span class="text-sm font-semibold">PaishaPay</span>
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
                            <div class="lp-card p-6">
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
                            @php
                                // Illustrative ETH→base-currency swap, priced live (user's base, else USD).
                                $cvBase = \App\Support\BaseCurrency::displayCode();
                                $cvRate = (float) (app(\App\Domain\Exchange\CoinGeckoRateProvider::class)->ratesWithFallback($cvBase, ['ETH'])['ETH'] ?? 0);
                            @endphp
                            {{-- Swap widget --}}
                            <div class="lp-card p-6">
                                <p class="text-sm font-semibold text-slate-900">{{ __('Swap') }}</p>
                                <div class="relative mt-4 space-y-2">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                        <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('You pay') }}</p>
                                        <div class="mt-1 flex items-center justify-between">
                                            <span class="text-2xl font-bold text-slate-900">1.00</span>
                                            <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">ETH</span>
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
                                            <span class="text-2xl font-bold text-slate-900">{{ number_format($cvRate, 2) }}</span>
                                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $cvBase }}</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-3 text-center text-xs text-slate-400">{{ __('Rate 1 ETH ≈ :rate :currency · spread shown up front', ['rate' => number_format($cvRate, 2), 'currency' => $cvBase]) }}</p>
                            </div>
                            @break

                        @case('shop')
                            {{-- Physical-product checkout mockup --}}
                            <div class="lp-card p-6">
                                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <span class="relative grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-xl text-white shadow-inner" style="background:linear-gradient(135deg,#334155,#0f172a)">
                                            <x-heroicon-o-musical-note class="h-6 w-6 opacity-90" />
                                            <span aria-hidden="true" class="absolute inset-0 opacity-20" style="background-image:radial-gradient(circle at 30% 25%,#fff 1px,transparent 1px);background-size:10px 10px"></span>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900">{{ __('Aurora Headphones') }}</p>
                                            <span class="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide" style="color:var(--brand);background:color-mix(in srgb,var(--brand) 10%,transparent)">
                                                <x-heroicon-s-cube class="h-3 w-3" /> {{ __('Physical') }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-4 flex items-baseline gap-2">
                                        <span class="text-2xl font-bold text-slate-900">$129</span>
                                        <span class="text-sm text-slate-400 line-through">$179</span>
                                        <span class="ml-auto inline-flex items-center gap-1 text-[11px] font-semibold" style="color:var(--up)">
                                            <x-heroicon-s-truck class="h-3.5 w-3.5" /> {{ __('Free shipping') }}
                                        </span>
                                    </div>
                                    <div class="mt-3 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-[11px] text-slate-500">
                                        <x-heroicon-o-map-pin class="h-3.5 w-3.5 flex-none" style="color:var(--brand)" />
                                        {{ __('Ships in 2–3 days · address at checkout') }}
                                    </div>
                                    <button type="button" class="mt-4 w-full rounded-xl py-3 text-sm font-semibold text-white shadow-sm" style="background:var(--brand)">{{ __('Buy now') }}</button>
                                    <div class="mt-3 flex items-center gap-2 rounded-xl border-2 border-dashed p-3 text-xs" style="border-color:color-mix(in srgb,var(--brand) 40%,transparent);background:color-mix(in srgb,var(--brand) 5%,transparent)">
                                        <span class="grid h-5 w-5 shrink-0 place-items-center rounded-md text-white" style="background:var(--brand)"><x-heroicon-s-plus class="h-3.5 w-3.5" /></span>
                                        <span class="font-medium text-slate-700">{{ __('Add the carrying case') }}</span>
                                        <span class="ml-auto font-semibold" style="color:var(--brand)">+$19</span>
                                    </div>
                                </div>
                                <p class="mt-4 inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                                    <x-heroicon-o-bolt class="h-3.5 w-3.5" style="color:var(--brand)" /> {{ __('Wallet · card · crypto checkout') }}
                                </p>
                            </div>
                            @break

                        @case('merchant-pay')
                            {{-- Invoice / QR --}}
                            <div class="lp-card p-6 text-center">
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ __('Invoice · INV-1042') }}</p>
                                <p class="mt-1 text-3xl font-bold tracking-tight text-slate-900">50.00 <span class="text-base font-semibold text-slate-400">USDT</span></p>
                                <div class="relative mx-auto mt-5 h-44 w-44 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                                    <svg viewBox="0 0 29 29" class="h-full w-full" shape-rendering="crispEdges" role="img" aria-label="{{ __('Payment QR code') }}">
                                        <rect width="29" height="29" fill="#fff"/>
                                        @php $finders = [[0,0],[22,0],[0,22]]; @endphp
                                        @foreach ($finders as $f)
                                            <rect x="{{ $f[0] }}" y="{{ $f[1] }}" width="7" height="7" rx="1.5" fill="#0f172a"/>
                                            <rect x="{{ $f[0]+1 }}" y="{{ $f[1]+1 }}" width="5" height="5" rx="1" fill="#fff"/>
                                            <rect x="{{ $f[0]+2 }}" y="{{ $f[1]+2 }}" width="3" height="3" rx="0.6" style="fill:var(--brand)"/>
                                        @endforeach
                                        @for ($y = 0; $y < 29; $y++)
                                            @for ($x = 0; $x < 29; $x++)
                                                @php
                                                    $inFinder = ($x < 8 && $y < 8) || ($x > 20 && $y < 8) || ($x < 8 && $y > 20);
                                                    $inLogo = $x >= 11 && $x <= 17 && $y >= 11 && $y <= 17;
                                                @endphp
                                                @if (! $inFinder && ! $inLogo && (($x * 7 + $y * 13 + $x * $y) % 3 === 0))
                                                    <rect x="{{ $x }}" y="{{ $y }}" width="1" height="1" rx="0.3" fill="#0f172a"/>
                                                @endif
                                            @endfor
                                        @endfor
                                    </svg>
                                    <span class="absolute left-1/2 top-1/2 grid h-9 w-9 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-xl text-white shadow-md" style="background:linear-gradient(135deg,var(--brand),var(--brand-600))">
                                        <x-heroicon-s-bolt class="h-5 w-5" />
                                    </span>
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

    {{-- ═══════════ Features ═══════════ --}}
    <section class="mx-auto mt-20 max-w-7xl px-4 sm:mt-28 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-xl text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.16em]" style="color:var(--brand)">{{ __('Features') }}</p>
            <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('Everything you need') }}</h2>
        </div>
        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($product['features'] as $f)
                <div class="lp-glass lp-card-hover flex gap-4 rounded-2xl p-6">
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

    {{-- ═══════════ Showcase grid ═══════════ --}}
    @if (! empty($product['grid']))
        <section class="mx-auto mt-20 max-w-7xl px-4 sm:mt-28 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-xl text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.16em]" style="color:var(--brand)">{{ __($product['grid']['eyebrow']) }}</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __($product['grid']['title']) }}</h2>
                @if (! empty($product['grid']['lead']))
                    <p class="mt-3 text-base text-slate-600">{{ __($product['grid']['lead']) }}</p>
                @endif
            </div>
            <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($product['grid']['items'] as $t)
                    <div class="group flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-blue-500/40 hover:shadow-md">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl ring-1 ring-slate-200 transition group-hover:ring-blue-500/30" style="color:var(--brand);background:color-mix(in srgb,var(--brand) 8%,transparent)">
                            <x-dynamic-component :component="'heroicon-o-'.$t['icon']" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">{{ __($t['name']) }}</h3>
                            <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ __($t['desc']) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ═══════════ How it works ═══════════ --}}
    @if (! empty($product['steps']))
        <section class="mx-auto mt-20 max-w-7xl px-4 sm:mt-28 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-xl text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.16em]" style="color:var(--brand)">{{ __('How it works') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('Up and running in minutes') }}</h2>
            </div>
            @php $stepCols = count($product['steps']) === 4 ? 'md:grid-cols-4' : 'md:grid-cols-3'; @endphp
            <div class="relative mt-16">
                {{-- connector line (desktop) --}}
                <div aria-hidden="true" class="absolute left-0 right-0 top-8 hidden h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent md:block"></div>
                <div class="grid gap-10 {{ $stepCols }} md:gap-6">
                    @foreach ($product['steps'] as $i => $step)
                        <div class="relative text-center">
                            <div class="relative mx-auto grid h-16 w-16 place-items-center rounded-2xl border border-slate-200 bg-white shadow-sm">
                                @if (! empty($step['icon']))
                                    <x-dynamic-component :component="'heroicon-o-'.$step['icon']" class="h-7 w-7" style="color:var(--brand)" />
                                    <span class="absolute -right-1.5 -top-1.5 grid h-6 w-6 place-items-center rounded-full text-xs font-bold text-white shadow" style="background:var(--brand)">{{ $i + 1 }}</span>
                                @else
                                    <span class="text-lg font-bold" style="color:var(--brand)">{{ $i + 1 }}</span>
                                @endif
                            </div>
                            <h3 class="mt-5 text-base font-semibold text-slate-900">{{ $step['title'] }}</h3>
                            <p class="mx-auto mt-1.5 max-w-[15rem] text-sm leading-relaxed text-slate-600">{{ $step['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ═══════════ Pricing ═══════════ --}}
    @if (! empty($product['pricing']))
        <section id="pricing" class="mx-auto mt-20 max-w-7xl px-4 scroll-mt-24 sm:mt-28 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-xl text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.16em]" style="color:var(--brand)">{{ __('Pricing') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('Simple plans that grow with you') }}</h2>
                <p class="mt-3 text-base text-slate-600">{{ __('Start free. Upgrade for more products and a lower commission on every sale.') }}</p>
            </div>
            <div class="mt-12 grid items-start gap-6 lg:grid-cols-3">
                @foreach ($product['pricing'] as $plan)
                    @php $popular = ! empty($plan['popular']); @endphp
                    <div @class([
                        'relative flex flex-col rounded-3xl bg-white p-7 transition',
                        'border-2 shadow-xl shadow-blue-900/10 lg:-mt-4 lg:mb-4' => $popular,
                        'border border-slate-200 shadow-sm' => ! $popular,
                    ]) @style(['border-color:var(--brand)' => $popular])>
                        @if ($popular)
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white shadow-sm" style="background:var(--brand)">{{ __('Most popular') }}</span>
                        @endif
                        <h3 class="text-lg font-bold text-slate-900">{{ __($plan['name']) }}</h3>
                        <div class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-4xl font-extrabold tracking-tight text-slate-900">{{ $plan['price'] }}</span>
                            <span class="text-sm font-medium text-slate-400">{{ __($plan['period']) }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">{{ __($plan['tagline']) }}</p>
                        <ul class="mt-6 space-y-3 border-t border-slate-100 pt-6">
                            @foreach ($plan['features'] as $feat)
                                <li class="flex items-start gap-2.5 text-sm text-slate-700">
                                    <x-heroicon-s-check-circle class="mt-0.5 h-4 w-4 flex-none" style="color:var(--brand)" />
                                    <span>{{ __($feat) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ ! empty($product['cta']) ? route($product['cta']['route']) : route('register') }}"
                            @class([
                                'lp-btn lp-btn-lg mt-8 w-full',
                                'lp-btn-primary' => $popular,
                                'lp-btn-ghost' => ! $popular,
                            ])>{{ __('Start selling') }}</a>
                    </div>
                @endforeach
            </div>
            <p class="mt-6 text-center text-xs text-slate-400">{{ __('Commission is deducted per sale — no separate payment-processing fees.') }}</p>
        </section>
    @endif

    {{-- ═══════════ FAQ ═══════════ --}}
    @if (! empty($product['faqs']))
        <section class="mx-auto mt-20 max-w-3xl px-4 pb-24 sm:mt-28 sm:px-6 lg:px-8" x-data="{ open: 0 }">
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
</x-landing::layouts.master>
