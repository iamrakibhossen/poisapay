@php
    $features = [
        ['icon' => 'link', 'title' => __('Payment links'), 'body' => __('Create a link, share it anywhere, get paid in crypto.')],
        ['icon' => 'qr-code', 'title' => __('QR at checkout'), 'body' => __('Show a QR in-store — customers scan and pay in a tap.')],
        ['icon' => 'document-text', 'title' => __('Invoices'), 'body' => __('Send branded invoices with due dates and auto-reminders.')],
        ['icon' => 'bolt', 'title' => __('Instant settlement'), 'body' => __('Funds land in your PoisaPay wallet the moment they clear.')],
        ['icon' => 'banknotes', 'title' => __('Low flat fee'), 'body' => __('A simple 1% per transaction. No setup or monthly fees.')],
        ['icon' => 'code-bracket', 'title' => __('API & webhooks'), 'body' => __('Automate orders and reconciliation with a clean REST API.')],
        ['icon' => 'arrow-uturn-left', 'title' => __('Refunds'), 'body' => __('One-tap refunds and clear dispute handling.')],
        ['icon' => 'chart-bar', 'title' => __('Dashboard'), 'body' => __('Track sales, settlements and payouts in real time.')],
    ];
    $featured = array_shift($features); // "Payment links" becomes the bento hero tile

    $steps = [
        ['icon' => 'user-plus', 'title' => __('Create & verify'), 'body' => __('Sign up and complete business KYC once.')],
        ['icon' => 'building-storefront', 'title' => __('Set up your store'), 'body' => __('Add your business details and settlement asset.')],
        ['icon' => 'qr-code', 'title' => __('Share link or QR'), 'body' => __('Send a payment link or show a QR at checkout.')],
        ['icon' => 'banknotes', 'title' => __('Get paid'), 'body' => __('Settle instantly to your wallet, cash out anytime.')],
    ];

    $stats = [
        ['value' => '1%', 'label' => __('Flat fee, all-in')],
        ['value' => __('Instant'), 'label' => __('Wallet settlement')],
        ['value' => '5+', 'label' => __('Coins accepted')],
        ['value' => '৳0', 'label' => __('Setup & monthly')],
    ];

    $coins = ['USDT', 'BTC', 'ETH', 'BNB', 'TON'];
@endphp

<x-layouts.marketing :title="__('Accept crypto payments')" :description="__('Accept USDT, BTC and ETH with payment links, invoices and QR checkout. Instant settlement to your PoisaPay wallet, a flat 1% fee, and a clean API. Built for Bangladesh.')">

    {{-- ===================== HERO ===================== --}}
    <section class="relative overflow-hidden px-4 pb-16 pt-8 sm:px-6 lg:px-8 lg:pb-20 lg:pt-16">
        <div class="mx-auto max-w-7xl">
            <div class="grid items-center gap-14 lg:grid-cols-[1.05fr_1fr] lg:gap-8">
                {{-- LEFT --}}
                <div class="text-center lg:text-left">
                    <span class="reveal inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-medium text-slate-700 ring-chip">
                        <span class="h-1.5 w-1.5 rounded-full pp-pulse" style="background:var(--brand)"></span>
                        {{ __('PoisaPay for Business') }}
                    </span>

                    <h1 class="reveal mx-auto mt-6 max-w-2xl text-[2.7rem] font-extrabold leading-[1.03] tracking-tight text-slate-900 sm:text-6xl lg:mx-0 lg:text-[4rem]" data-d="1">
                        {{ __('Accept crypto.') }} <span class="grad-text-anim">{{ __('Settle instantly.') }}</span>
                    </h1>

                    <p class="reveal mx-auto mt-6 max-w-xl text-lg leading-relaxed text-slate-600 lg:mx-0" data-d="2">
                        {{ __('Take payments in USDT, BTC and ETH with payment links, invoices and QR checkout — settled straight to your PoisaPay wallet. A flat 1% fee, no setup cost.') }}
                    </p>

                    <div class="reveal mt-9 flex flex-wrap items-center justify-center gap-3 lg:justify-start" data-d="3">
                        <a href="{{ route('register') }}" class="pp-btn pp-btn-primary pp-btn-lg">
                            {{ __('Get started') }} <x-heroicon-o-arrow-right class="h-5 w-5" />
                        </a>
                        <a href="#how" class="pp-btn pp-btn-ghost pp-btn-lg">
                            <x-heroicon-s-play class="h-4 w-4" /> {{ __('How it works') }}
                        </a>
                    </div>

                    <div class="reveal mt-10 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 lg:justify-start" data-d="4">
                        @foreach ([['check-badge', __('No chargebacks')],['globe-alt', __('Multi-coin')],['code-bracket', __('Developer API')],['shield-check', __('KYC / AML')]] as $b)
                            <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                                <x-dynamic-component :component="'heroicon-s-'.$b[0]" class="h-4 w-4" style="color:var(--brand)" />
                                {{ $b[1] }}
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- RIGHT: payment request mock --}}
                <div class="relative mx-auto w-full max-w-sm">
                    <div aria-hidden="true" class="absolute inset-0 -z-10 blur-3xl" style="background:radial-gradient(circle at 60% 30%,rgba(37,99,235,.16),transparent 65%)"></div>

                    <div class="reveal glass-card pp-float-slow p-6" data-d="2">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-slate-900">{{ __('Payment request') }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ __('Invoice #INV-2K9F') }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" style="background:rgba(37,99,235,.10);color:var(--brand)">USDT</span>
                        </div>

                        <p class="mt-4 text-3xl font-bold tabular text-slate-900">$250.00</p>
                        <p class="text-xs text-slate-500">{{ __('Scan to pay or share the link') }}</p>

                        {{-- QR (decorative mock) --}}
                        <div class="mt-4 grid place-items-center rounded-2xl border border-slate-200 bg-white p-4">
                            <svg viewBox="0 0 25 25" class="h-40 w-40" shape-rendering="crispEdges" role="img" aria-label="{{ __('Payment QR code') }}">
                                <rect width="25" height="25" fill="#fff"/>
                                @php $finders = [[0,0],[18,0],[0,18]]; @endphp
                                @foreach ($finders as $f)
                                    <rect x="{{ $f[0] }}" y="{{ $f[1] }}" width="7" height="7" fill="#0f172a"/>
                                    <rect x="{{ $f[0]+1 }}" y="{{ $f[1]+1 }}" width="5" height="5" fill="#fff"/>
                                    <rect x="{{ $f[0]+2 }}" y="{{ $f[1]+2 }}" width="3" height="3" fill="#2563eb"/>
                                @endforeach
                                @for ($y = 0; $y < 25; $y++)
                                    @for ($x = 0; $x < 25; $x++)
                                        @php $inFinder = ($x < 8 && $y < 8) || ($x > 16 && $y < 8) || ($x < 8 && $y > 16); @endphp
                                        @if (! $inFinder && (($x * 7 + $y * 13 + $x * $y) % 3 === 0))
                                            <rect x="{{ $x }}" y="{{ $y }}" width="1" height="1" fill="#0f172a"/>
                                        @endif
                                    @endfor
                                @endfor
                            </svg>
                        </div>

                        <div class="mt-4 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2.5">
                            <x-heroicon-o-link class="h-4 w-4 flex-none text-slate-400" />
                            <span class="truncate text-xs text-slate-600">pay.poisapay.app/inv_2K9F</span>
                            <span class="ml-auto text-xs font-semibold" style="color:var(--brand)">{{ __('Copy') }}</span>
                        </div>
                    </div>

                    {{-- floating chip --}}
                    <div class="absolute -bottom-6 -left-3 z-30 hidden sm:block pp-float">
                        <div class="glass-2 flex items-center gap-2.5 rounded-2xl px-3.5 py-2.5 shadow-[var(--shadow-card)]">
                            <span class="grid h-8 w-8 place-items-center rounded-full" style="background:rgba(16,185,129,.14)">
                                <x-heroicon-s-check class="h-4 w-4" style="color:var(--up)" />
                            </span>
                            <div class="leading-tight">
                                <p class="text-xs font-semibold text-slate-900">{{ __('Settled to wallet') }}</p>
                                <p class="text-[0.65rem] text-slate-500">{{ __( '+250.00 USDT · just now') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Stats band — hairline dividers via gap-px over a tinted parent --}}
            <div class="reveal mt-16 grid grid-cols-2 gap-px overflow-hidden rounded-3xl sm:grid-cols-4" style="background:rgba(226,232,240,.7)" data-d="2">
                @foreach ($stats as $s)
                    <div class="bg-white/70 px-6 py-7 text-center backdrop-blur">
                        <p class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ $s['value'] }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $s['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== FEATURES (bento) ===================== --}}
    <section id="features" class="relative px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="mx-auto max-w-7xl">
            <div class="reveal mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.18em]" style="color:var(--brand)">{{ __('Everything to get paid') }}</p>
                <h2 class="mt-3 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">{{ __('Payments that just') }} <span class="grad-text">{{ __('work.') }}</span></h2>
                <p class="mt-4 text-lg text-slate-600">{{ __('Links, invoices, QR and an API — settled to your wallet in real time.') }}</p>
            </div>

            <div class="mt-14 grid gap-4 sm:grid-cols-2 lg:auto-rows-fr lg:grid-cols-4">
                {{-- Featured tile (2×2) --}}
                <div class="reveal group relative overflow-hidden rounded-3xl p-8 sm:col-span-2 lg:row-span-2 lg:flex lg:flex-col"
                    style="background:linear-gradient(150deg,rgba(37,99,235,.10),rgba(14,165,233,.06))" data-d="1">
                    <span aria-hidden="true" class="absolute -right-16 -top-16 h-56 w-56 rounded-full blur-3xl" style="background:radial-gradient(circle,rgba(37,99,235,.20),transparent 65%)"></span>
                    <span class="relative grid h-14 w-14 place-items-center rounded-2xl text-white shadow-lg" style="background:linear-gradient(120deg,var(--brand),var(--brand-600))">
                        <x-dynamic-component :component="'heroicon-o-'.$featured['icon']" class="h-7 w-7" />
                    </span>
                    <h3 class="relative mt-6 text-2xl font-bold text-slate-900">{{ $featured['title'] }}</h3>
                    <p class="relative mt-2 max-w-sm text-slate-600">{{ $featured['body'] }} {{ __('Share on WhatsApp, social or email — no code required.') }}</p>

                    {{-- mock link pill --}}
                    <div class="relative mt-6 flex items-center gap-2 rounded-xl border border-slate-200 bg-white/80 px-3.5 py-3 backdrop-blur lg:mt-auto">
                        <x-heroicon-o-link class="h-4 w-4 flex-none text-slate-400" />
                        <span class="truncate text-sm text-slate-600">pay.poisapay.app/tea-house</span>
                        <span class="ml-auto inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-white" style="background:var(--brand)">
                            <x-heroicon-o-share class="h-3.5 w-3.5" /> {{ __('Share') }}
                        </span>
                    </div>
                </div>

                {{-- Remaining feature tiles --}}
                @foreach ($features as $i => $f)
                    <div class="reveal glass glass-hover rounded-3xl p-6" data-d="{{ ($i % 3) + 1 }}">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl" style="background:rgba(37,99,235,.10)">
                            <x-dynamic-component :component="'heroicon-o-'.$f['icon']" class="h-6 w-6" style="color:var(--brand)" />
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $f['title'] }}</h3>
                        <p class="mt-1.5 text-sm text-slate-500">{{ $f['body'] }}</p>
                    </div>
                @endforeach

                {{-- Accepted coins filler tile (fills the bento) --}}
                <div class="reveal glass glass-hover flex flex-col justify-center rounded-3xl p-6" data-d="2">
                    <p class="text-sm font-semibold text-slate-900">{{ __('Accepted coins') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($coins as $c)
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold text-slate-700 ring-chip">{{ $c }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== HOW IT WORKS (timeline) ===================== --}}
    <section id="how" class="relative px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="mx-auto max-w-7xl">
            <div class="reveal mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.18em]" style="color:var(--brand)">{{ __('How it works') }}</p>
                <h2 class="mt-3 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">{{ __('Live in') }} <span class="grad-text">{{ __('four steps.') }}</span></h2>
            </div>

            <div class="relative mt-16">
                {{-- connector line (desktop) --}}
                <div aria-hidden="true" class="absolute left-0 right-0 top-8 hidden h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent md:block"></div>

                <div class="grid gap-10 md:grid-cols-4 md:gap-6">
                    @foreach ($steps as $i => $s)
                        <div class="reveal relative text-center" data-d="{{ $i + 1 }}">
                            <div class="relative mx-auto grid h-16 w-16 place-items-center rounded-2xl glass-2">
                                <x-dynamic-component :component="'heroicon-o-'.$s['icon']" class="h-7 w-7" style="color:var(--brand)" />
                                <span class="absolute -right-1.5 -top-1.5 grid h-6 w-6 place-items-center rounded-full text-xs font-bold text-white shadow" style="background:var(--brand)">{{ $i + 1 }}</span>
                            </div>
                            <h3 class="mt-5 text-base font-semibold text-slate-900">{{ $s['title'] }}</h3>
                            <p class="mx-auto mt-1.5 max-w-[15rem] text-sm text-slate-500">{{ $s['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== CTA (gradient) ===================== --}}
    <section class="relative px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="mx-auto max-w-5xl">
            <div class="reveal glass-card relative overflow-hidden p-10 text-center sm:p-16">
                <span aria-hidden="true" class="absolute -left-16 -top-16 h-64 w-64 rounded-full blur-3xl" style="background:radial-gradient(circle,rgba(37,99,235,.16),transparent 65%)"></span>
                <span aria-hidden="true" class="absolute -bottom-16 -right-16 h-64 w-64 rounded-full blur-3xl" style="background:radial-gradient(circle,rgba(14,165,233,.14),transparent 65%)"></span>
                <div class="relative">
                    <h2 class="text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">{{ __('Start accepting crypto') }} <span class="grad-text-anim">{{ __('today.') }}</span></h2>
                    <p class="mx-auto mt-4 max-w-lg text-lg text-slate-600">{{ __('Create your merchant account and share your first payment link in minutes.') }}</p>
                    <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('register') }}" class="pp-btn pp-btn-primary pp-btn-lg">{{ __('Get started') }} <x-heroicon-o-arrow-right class="h-5 w-5" /></a>
                        @auth
                            <a href="{{ route('merchant') }}" class="pp-btn pp-btn-ghost pp-btn-lg"><x-heroicon-s-building-storefront class="h-5 w-5" /> {{ __('Open merchant console') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="pp-btn pp-btn-ghost pp-btn-lg"><x-heroicon-s-building-storefront class="h-5 w-5" /> {{ __('Merchant login') }}</a>
                        @endauth
                    </div>
                    <p class="mt-6 flex items-center justify-center gap-2 text-xs text-slate-500">
                        <x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> {{ __('Custodial · KYC/AML gated · Flat 1% per transaction') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

</x-layouts.marketing>
