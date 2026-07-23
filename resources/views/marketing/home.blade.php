@php
    /* ---- Static, mocked marketing data (no backend coupling) ---- */
    $trustBadges = [
        ['icon' => 'shield-check', 'label' => __('100% Secure')],
        ['icon' => 'check-badge', 'label' => __('KYC Verified')],
        ['icon' => 'globe-alt', 'label' => __('Multi-chain')],
        ['icon' => 'bolt', 'label' => __('Instant deposit')],
    ];

    $why = [
        ['icon' => 'arrow-down-tray', 'title' => __('Instant deposit'), 'body' => __('On-chain funds land in seconds — no waiting, no holds.')],
        ['icon' => 'arrow-up-tray', 'title' => __('Instant withdrawal'), 'body' => __('Cash out to crypto or Taka whenever you want.')],
        ['icon' => 'credit-card', 'title' => __('Virtual card'), 'body' => __('Spend your balance anywhere Mastercard is accepted.')],
        ['icon' => 'globe-alt', 'title' => __('Multi-chain'), 'body' => __('Ethereum, BNB Chain and Tron in one place.')],
        ['icon' => 'lock-closed', 'title' => __('Secure wallet'), 'body' => __('Custodial cold storage with 2FA on every action.')],
        ['icon' => 'building-storefront', 'title' => __('Merchant pay'), 'body' => __('Accept payments and settle instantly.')],
        ['icon' => 'paper-airplane', 'title' => __('Fast transfers'), 'body' => __('Free P2P by PoisaPay ID, email or phone.')],
        ['icon' => 'qr-code', 'title' => __('QR pay'), 'body' => __('Scan to pay or get paid in a tap.')],
    ];

    $security = [
        ['icon' => 'lock-closed', 'title' => __('256-bit encryption'), 'body' => __('Every byte in transit and at rest is encrypted end to end.')],
        ['icon' => 'cube-transparent', 'title' => __('Cold + hot split'), 'body' => __('The vast majority of funds sit offline in segregated custody.')],
        ['icon' => 'document-check', 'title' => __('AML & KYC'), 'body' => __('Continuous monitoring keeps the platform clean and compliant.')],
        ['icon' => 'credit-card', 'title' => __('PCI-ready'), 'body' => __('Card infrastructure built to PCI-DSS standards.')],
        ['icon' => 'finger-print', 'title' => __('Biometric login'), 'body' => __('Face and fingerprint unlock on supported devices.')],
        ['icon' => 'shield-exclamation', 'title' => __('Fraud protection'), 'body' => __('Real-time risk scoring blocks suspicious activity.')],
    ];

    $assets = [
        ['sym' => 'USDT', 'name' => 'Tether', 'amount' => '8,240.00', 'value' => '$8,240', 'change' => '+0.01%', 'up' => true, 'pct' => 46, 'color' => '#26a17b'],
        ['sym' => 'BTC', 'name' => 'Bitcoin', 'amount' => '0.0642', 'value' => '$4,180', 'change' => '+2.4%', 'up' => true, 'pct' => 24, 'color' => '#f7931a'],
        ['sym' => 'ETH', 'name' => 'Ethereum', 'amount' => '1.284', 'value' => '$2,910', 'change' => '+1.1%', 'up' => true, 'pct' => 16, 'color' => '#627eea'],
        ['sym' => 'BNB', 'name' => 'BNB', 'amount' => '4.10', 'value' => '$1,760', 'change' => '-0.6%', 'up' => false, 'pct' => 9, 'color' => '#f3ba2f'],
        ['sym' => 'TON', 'name' => 'Toncoin', 'amount' => '210.0', 'value' => '$890', 'change' => '+3.2%', 'up' => true, 'pct' => 5, 'color' => '#0098ea'],
    ];

    $faqs = [
        ['q' => __('Is PoisaPay available in Bangladesh?'), 'a' => __('Yes — PoisaPay is built for Bangladesh, with native Taka support alongside crypto and instant local P2P transfers.')],
        ['q' => __('Do I need to complete KYC?'), 'a' => __('Basic features work right away. Full verification unlocks higher limits, the virtual card and fiat cash-out, and keeps your account compliant.')],
        ['q' => __('Which networks are supported?'), 'a' => __('Ethereum, BNB Smart Chain and Tron today, with Tron-first custody for fast, low-cost transfers. More chains are on the roadmap.')],
        ['q' => __('How fast can I get a virtual card?'), 'a' => __('Once verified, a card is issued in seconds and can be added to Apple Pay or Google Pay right away.')],
        ['q' => __('How are my funds secured?'), 'a' => __('Funds sit in custodial cold storage with 2FA, device controls and continuous monitoring. Every balance is tracked on an exact double-entry ledger.')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PoisaPay · Spend crypto like cash, with a premium virtual card</title>
    <meta name="description" content="A premium crypto wallet with a beautiful virtual card. Hold, send and spend crypto and Taka anywhere — instant deposits, Apple & Google Pay, bank-grade custody. Built for Bangladesh.">
    <meta property="og:title" content="PoisaPay · Spend crypto like cash">
    <meta property="og:description" content="A premium crypto wallet with a beautiful virtual card. Spend crypto anywhere.">
    <meta property="og:type" content="website">
    @vite(['resources/css/app.css', 'resources/js/frontend.js'])

    @include('partials.marketing-styles')
</head>
<body class="poisa-landing h-full antialiased">

<div class="pp-mesh" aria-hidden="true"></div>
<div class="pp-grid-overlay" aria-hidden="true"></div>

<div class="relative z-10 min-h-full">

    <x-marketing.nav />

    {{-- ===================== HERO ===================== --}}
    <section id="exchange" class="relative overflow-hidden px-4 pb-16 pt-32 sm:px-6 lg:px-8 lg:pb-24 lg:pt-40">
        <div class="mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-[1.05fr_1fr] lg:gap-8">
            {{-- LEFT --}}
            <div class="text-center lg:text-left">
                <span class="reveal inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-medium text-slate-700 ring-chip">
                    <span class="h-1.5 w-1.5 rounded-full pp-pulse" style="background:var(--brand)"></span>
                    {{ __('Now live · Tron-first custody') }}
                </span>

                <h1 class="reveal mx-auto mt-6 max-w-2xl text-[2.7rem] font-extrabold leading-[1.03] tracking-tight text-slate-900 sm:text-6xl lg:mx-0 lg:text-[4.2rem]" data-d="1">
                    {{ __('Spend crypto') }} <br class="hidden sm:block" />{{ __('like') }} <span class="grad-text-anim">{{ __('cash.') }}</span>
                </h1>

                <p class="reveal mx-auto mt-6 max-w-xl text-lg leading-relaxed text-slate-600 lg:mx-0" data-d="2">
                    {{ __('A premium crypto wallet with a beautiful virtual card. Hold, send and spend crypto and Taka anywhere — instant deposits, Apple & Google Pay, bank-grade custody.') }}
                </p>

                <div class="reveal mt-9 flex flex-wrap items-center justify-center gap-3 lg:justify-start" data-d="3">
                    <a href="{{ route('register') }}" class="pp-btn pp-btn-primary pp-btn-lg">
                        {{ __('Get started') }} <x-heroicon-o-arrow-right class="h-5 w-5" />
                    </a>
                    <a href="#wallet" class="pp-btn pp-btn-ghost pp-btn-lg">
                        <x-heroicon-s-play class="h-4 w-4" /> {{ __('View demo') }}
                    </a>
                </div>

                {{-- Trust badges --}}
                <div class="reveal mt-10 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 lg:justify-start" data-d="4">
                    @foreach ($trustBadges as $b)
                        <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                            <x-dynamic-component :component="'heroicon-s-'.$b['icon']" class="h-4 w-4" style="color:var(--brand)" />
                            {{ $b['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- RIGHT: live exchange converter (hero highlight) --}}
            <div class="relative mx-auto w-full max-w-md">
                <div class="reveal relative z-10" data-d="1">
                    <x-marketing.converter id="hero" />
                </div>
            </div>
        </div>

        {{-- Logo marquee --}}
        <div class="reveal pp-marquee-mask mx-auto mt-20 max-w-6xl overflow-hidden">
            <p class="mb-6 text-center text-xs font-medium uppercase tracking-[0.2em] text-slate-400">{{ __('Works everywhere you already pay') }}</p>
            <div class="pp-marquee">
                @foreach (['Mastercard','Apple Pay','Google Pay','Visa','Tron','Ethereum','BNB Chain','USDT','Tap to Pay','Mastercard','Apple Pay','Google Pay','Visa','Tron','Ethereum','BNB Chain','USDT','Tap to Pay'] as $logo)
                    <span class="whitespace-nowrap text-lg font-semibold text-slate-400">{{ $logo }}</span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== LIVE PRICES ===================== --}}
    <section id="prices" class="relative px-4 py-20 sm:px-6 lg:px-8 lg:py-24">
        @php
            $priceCoins = ['BTC', 'ETH', 'USDT', 'BNB', 'USDC', 'TRX'];
            $priceNames = ['BTC' => 'Bitcoin', 'ETH' => 'Ethereum', 'USDT' => 'Tether', 'BNB' => 'BNB', 'USDC' => 'USD Coin', 'TRX' => 'TRON'];
            $liveRates = app(\App\Domain\Exchange\CoinGeckoRateProvider::class)->bdtRatesWithFallback($priceCoins);
        @endphp
        <div class="mx-auto max-w-6xl">
            <div class="mx-auto max-w-xl text-center reveal">
                <p class="text-sm font-semibold uppercase tracking-[0.16em]" style="color:var(--brand)">{{ __('Live prices') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('Crypto prices, live in Taka') }}</h2>
                <p class="mt-3 text-slate-600">{{ __('Real-time reference rates, refreshed continuously.') }}</p>
            </div>

            <div class="reveal mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-price-grid data-rates-url="{{ route('marketing.rates') }}">
                @foreach ($priceCoins as $sym)
                    <a href="{{ route('marketing.rates') }}" class="glass glass-hover flex items-center gap-4 rounded-2xl p-5">
                        <x-ui.asset-icon :symbol="$sym" size="lg" class="shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-900">{{ $sym }}</p>
                            <p class="truncate text-xs text-slate-400">{{ $priceNames[$sym] ?? $sym }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="tabular text-base font-bold text-slate-900" data-price data-sym="{{ $sym }}">৳{{ number_format((float) ($liveRates[$sym] ?? 0), 2) }}</p>
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">BDT</p>
                        </div>
                    </a>
                @endforeach
            </div>

            <p class="mt-5 flex items-center justify-center gap-1.5 text-xs font-medium text-slate-400">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 motion-safe:animate-pulse"></span>
                {{ __('Live · updates every minute') }} ·
                <a href="{{ route('marketing.rates') }}" class="text-slate-500 underline-offset-2 hover:text-slate-900 hover:underline">{{ __('View all rates') }}</a>
            </p>
        </div>

        @push('scripts')
        <style>@keyframes ppFlashP{0%{color:var(--brand)}100%{color:inherit}}.pp-price-flash{animation:ppFlashP .7s ease-out}</style>
        <script>
        (function () {
            var grid = document.querySelector('[data-price-grid]');
            if (!grid || !window.fetch) return;
            var url = grid.getAttribute('data-rates-url');
            function fmt(n) { return '৳' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
            function refresh() {
                if (document.hidden) return;
                fetch(url, { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (!data || !data.rates) return;
                        grid.querySelectorAll('[data-price][data-sym]').forEach(function (el) {
                            var v = data.rates[el.getAttribute('data-sym')];
                            if (v == null) return;
                            var t = fmt(v);
                            if (el.textContent !== t) { el.textContent = t; el.classList.remove('pp-price-flash'); void el.offsetWidth; el.classList.add('pp-price-flash'); }
                        });
                    }).catch(function () {});
            }
            setInterval(refresh, 60000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
        })();
        </script>
        @endpush
    </section>

    {{-- ===================== CARD SHOWCASE ===================== --}}
    <section id="cards" class="relative px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="mx-auto max-w-7xl">
            <div class="reveal mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.18em]" style="color:var(--brand)">{{ __('The card') }}</p>
                <h2 class="mt-3 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">{{ __('One card.') }} <span class="grad-text">{{ __('Total control.') }}</span></h2>
                <p class="mt-4 text-lg text-slate-600">{{ __('A premium virtual Mastercard, funded by your crypto. Freeze it, cap it, and add it to the wallet you already use.') }}</p>
            </div>

            <div class="mt-14 grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                {{-- Card stage --}}
                <div class="reveal relative mx-auto w-full max-w-sm lg:mr-auto">
                    <div aria-hidden="true" class="absolute inset-0 -z-10 blur-3xl" style="background:radial-gradient(circle at 50% 50%,rgba(37,99,235,.18),transparent 65%)"></div>
                    <div class="relative" style="perspective:1600px">
                        <x-marketing.wallet-card finish="aurora" balance="$12,480.55" number="4291" tilt />
                    </div>
                    {{-- Soft reflection --}}
                    <div aria-hidden="true" class="mx-auto mt-3 h-8 w-4/5 rounded-full blur-xl" style="background:radial-gradient(ellipse at center,rgba(37,99,235,.22),transparent 70%)"></div>
                </div>

                {{-- Feature grid --}}
                <div class="reveal" data-d="1">
                    @php $cardFeatures = [
                        ['icon' => 'pause-circle', 'title' => __('Freeze card'), 'body' => __('Lock and unlock instantly from your phone.')],
                        ['icon' => 'adjustments-horizontal', 'title' => __('Spend limits'), 'body' => __('Daily and per-transaction caps you control.')],
                        ['icon' => 'sparkles', 'title' => __('Apple & Google Pay'), 'body' => __('Add to your mobile wallet in a tap.')],
                        ['icon' => 'wifi', 'title' => __('Tap to pay'), 'body' => __('Contactless in stores, worldwide.')],
                        ['icon' => 'globe-alt', 'title' => __('Online & ATM'), 'body' => __('Pay online and withdraw cash anywhere.')],
                        ['icon' => 'bolt', 'title' => __('Instant funding'), 'body' => __('Top up from crypto in seconds.')],
                    ]; @endphp
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($cardFeatures as $cf)
                            <div class="glass glass-hover group rounded-2xl p-5">
                                <span class="grid h-11 w-11 place-items-center rounded-xl transition group-hover:scale-105" style="background:rgba(37,99,235,.10)">
                                    <x-dynamic-component :component="'heroicon-o-'.$cf['icon']" class="h-5 w-5" style="color:var(--brand)" />
                                </span>
                                <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $cf['title'] }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $cf['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-6 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                        <span class="inline-flex items-center gap-1.5 text-sm text-slate-500 sm:mr-auto">
                            <x-heroicon-s-check-badge class="h-4 w-4" style="color:var(--brand)" />
                            {{ __('No annual fee · Cancel anytime') }}
                        </span>
                        <a href="{{ route('register') }}" class="pp-btn pp-btn-primary pp-btn-lg w-full sm:w-auto">{{ __('Get your card') }} <x-heroicon-o-arrow-right class="h-5 w-5" /></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== WALLET SHOWCASE ===================== --}}
    <section id="wallet" class="relative px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-2 lg:gap-16">
            {{-- Copy --}}
            <div class="reveal">
                <p class="text-sm font-semibold uppercase tracking-[0.18em]" style="color:var(--brand)">{{ __('The wallet') }}</p>
                <h2 class="mt-3 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">{{ __('Your whole portfolio,') }} <span class="grad-text">{{ __('beautifully clear.') }}</span></h2>
                <p class="mt-4 text-lg text-slate-600">{{ __('Hold USDT, BTC, ETH, BNB and TON side by side with your Taka balance. Track allocation, swap instantly, and watch every move in real time.') }}</p>

                @php $walletPoints = [
                    ['title' => __('5+ assets, one balance'), 'body' => __('Crypto and Taka together, always in sync.')],
                    ['title' => __('Instant swaps, 24/7'),    'body' => __('Convert between assets at live rates in a tap.')],
                    ['title' => __('Zero-fee P2P'),           'body' => __('Send to any PoisaPay user with no charge.')],
                    ['title' => __('Real-time tracking'),     'body' => __('Allocation and returns update as markets move.')],
                ]; @endphp
                <ul class="mt-8 space-y-4">
                    @foreach ($walletPoints as $p)
                        <li class="flex items-start gap-3.5">
                            <span class="mt-0.5 grid h-6 w-6 flex-none place-items-center rounded-full" style="background:rgba(37,99,235,.12)">
                                <x-heroicon-o-check class="h-3.5 w-3.5" style="color:var(--brand)" stroke-width="2.5" />
                            </span>
                            <div>
                                <p class="font-semibold text-slate-900">{{ $p['title'] }}</p>
                                <p class="text-sm text-slate-500">{{ $p['body'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('register') }}" class="pp-btn pp-btn-primary pp-btn-lg mt-8 w-full sm:w-auto">{{ __('Open your wallet') }} <x-heroicon-o-arrow-right class="h-5 w-5" /></a>
            </div>

            {{-- Wallet app mock --}}
            <div class="reveal relative mx-auto w-full max-w-md" data-d="1">
                <div aria-hidden="true" class="absolute -inset-6 -z-10 blur-3xl" style="background:radial-gradient(circle at 60% 30%,rgba(37,99,235,.20),transparent 65%)"></div>

                <div class="glass-card overflow-hidden">
                    {{-- Gradient balance header --}}
                    <div class="relative overflow-hidden p-6 text-white" style="background:linear-gradient(135deg,#1e40af 0%,#2563eb 55%,#0ea5e9 100%)">
                        <span aria-hidden="true" class="card-sheen pointer-events-none absolute inset-0"></span>
                        <div class="relative flex items-start justify-between">
                            <div>
                                <p class="text-[0.62rem] font-medium uppercase tracking-[0.18em] text-white/70">{{ __('Total balance') }}</p>
                                <p class="mt-1 text-3xl font-bold tabular" data-count="17980" data-prefix="$" data-dec="2">$17,980.00</p>
                                <p class="mt-1 inline-flex items-center gap-1 text-sm font-medium tabular text-white/90">
                                    <x-heroicon-s-arrow-trending-up class="h-4 w-4" /> +$412.20 · 2.35% today
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2 py-0.5 text-[0.65rem] font-semibold backdrop-blur">
                                <span class="h-1.5 w-1.5 rounded-full pp-pulse" style="background:#fff"></span> Live
                            </span>
                        </div>

                        {{-- Quick actions --}}
                        @php $walletActions = [
                            ['icon' => 'paper-airplane',  'label' => __('Send')],
                            ['icon' => 'arrow-down-left',  'label' => __('Receive')],
                            ['icon' => 'arrows-right-left','label' => __('Swap')],
                        ]; @endphp
                        <div class="relative mt-6 grid grid-cols-3 gap-2">
                            @foreach ($walletActions as $act)
                                <div class="flex flex-col items-center gap-1.5 rounded-xl bg-white/10 py-3 backdrop-blur transition hover:bg-white/20">
                                    <x-dynamic-component :component="'heroicon-o-'.$act['icon']" class="h-5 w-5" />
                                    <span class="text-xs font-medium">{{ $act['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Allocation bar --}}
                    <div class="px-6 pt-5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-slate-500">{{ __('Allocation') }}</span>
                            <span class="text-slate-400">{{ count($assets) }} {{ __('assets') }}</span>
                        </div>
                        <div class="mt-2 flex h-2 gap-0.5 overflow-hidden rounded-full">
                            @foreach ($assets as $a)
                                <span class="h-full" style="width:{{ $a['pct'] }}%;background:{{ $a['color'] }}"></span>
                            @endforeach
                        </div>
                    </div>

                    {{-- Asset rows --}}
                    <div class="space-y-1 p-4 pt-3">
                        @foreach ($assets as $a)
                            <div class="flex items-center justify-between rounded-xl px-2 py-2 transition hover:bg-slate-50">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-9 w-9 place-items-center rounded-full text-[0.6rem] font-bold text-white" style="background:{{ $a['color'] }}">{{ substr($a['sym'],0,3) }}</span>
                                    <div class="leading-tight">
                                        <p class="text-sm font-semibold text-slate-900">{{ $a['name'] }}</p>
                                        <p class="text-xs text-slate-500 tabular">{{ $a['amount'] }} {{ $a['sym'] }}</p>
                                    </div>
                                </div>
                                <div class="text-right leading-tight">
                                    <p class="text-sm font-semibold text-slate-900 tabular">{{ $a['value'] }}</p>
                                    <p class="text-xs tabular" style="color:{{ $a['up'] ? 'var(--up)' : 'var(--down)' }}">{{ $a['change'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between border-t border-slate-100 px-6 py-4 text-xs">
                        <span class="inline-flex items-center gap-1.5 text-slate-400">
                            <span class="h-1.5 w-1.5 rounded-full pp-pulse" style="background:var(--up)"></span> {{ __('Updated just now') }}
                        </span>
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-1 font-semibold transition hover:gap-1.5" style="color:var(--brand)">
                            {{ __('Open wallet') }} <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== WHY POISAPAY ===================== --}}
    <section id="features" class="relative px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="mx-auto max-w-7xl">
            <div class="reveal mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.18em]" style="color:var(--brand)">{{ __('Why PoisaPay') }}</p>
                <h2 class="mt-3 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">{{ __('Everything you need to') }} <span class="grad-text">{{ __('move money.') }}</span></h2>
                <p class="mt-4 text-lg text-slate-600">{{ __('One account for crypto, Taka, transfers, exchange, cards and merchant payments.') }}</p>
            </div>

            <div class="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($why as $i => $w)
                    <div class="reveal glass glass-hover group relative overflow-hidden rounded-2xl p-6" data-d="{{ ($i % 4) + 1 }}">
                        {{-- Hover accent line --}}
                        <span aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-0.5 origin-left scale-x-0 transition-transform duration-300 group-hover:scale-x-100" style="background:linear-gradient(90deg,var(--brand),#0ea5e9)"></span>
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-[rgba(37,99,235,.10)] text-[color:var(--brand)] transition-all duration-300 group-hover:bg-gradient-to-br group-hover:from-[#2563eb] group-hover:to-[#0ea5e9] group-hover:text-white group-hover:shadow-lg group-hover:shadow-blue-500/25">
                            <x-dynamic-component :component="'heroicon-o-'.$w['icon']" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $w['title'] }}</h3>
                        <p class="mt-1.5 text-sm text-slate-500">{{ $w['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== SECURITY ===================== --}}
    <section id="security" class="relative overflow-hidden px-4 py-24 sm:px-6 lg:px-8 lg:py-32">
        {{-- Soft light backdrop --}}
        <div aria-hidden="true" class="absolute inset-0 -z-10 opacity-70" style="background:radial-gradient(circle at 18% 12%,rgba(37,99,235,.10),transparent 42%),radial-gradient(circle at 82% 20%,rgba(79,70,229,.09),transparent 45%)"></div>

        <div class="mx-auto max-w-7xl">
            {{-- Header --}}
            <div class="reveal mx-auto max-w-2xl text-center">
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] ring-1 ring-slate-200" style="background:rgba(37,99,235,.08);color:var(--brand)">
                    <x-heroicon-s-shield-check class="h-4 w-4" /> {{ __('Security') }}
                </span>
                <h2 class="mt-5 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">{{ __('Protection at') }} <span class="grad-text">{{ __('every layer.') }}</span></h2>
                <p class="mt-4 text-lg text-slate-600">{{ __('PoisaPay is custodial and compliance-first — cold storage, encryption and continuous monitoring on every balance.') }}</p>
            </div>

            {{-- Trust stats --}}
            @php $secStats = [
                ['value' => '$0',      'label' => __('Funds ever lost')],
                ['value' => '99.9%',   'label' => __('Held in cold storage')],
                ['value' => '256-bit', 'label' => __('Encryption')],
                ['value' => '24/7',    'label' => __('Threat monitoring')],
            ]; @endphp
            <div class="reveal mx-auto mt-12 grid max-w-3xl grid-cols-2 gap-4 sm:grid-cols-4" data-d="1">
                @foreach ($secStats as $st)
                    <div class="glass rounded-2xl p-4 text-center">
                        <p class="text-2xl font-bold tabular text-slate-900">{{ $st['value'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $st['label'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Controls grid --}}
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($security as $i => $sec)
                    <div class="reveal glass glass-hover group rounded-2xl p-6" data-d="{{ ($i % 3) + 1 }}">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-[rgba(37,99,235,.10)] text-[color:var(--brand)] transition-all duration-300 group-hover:bg-gradient-to-br group-hover:from-[#2563eb] group-hover:to-[#4f46e5] group-hover:text-white group-hover:shadow-lg group-hover:shadow-blue-500/25">
                            <x-dynamic-component :component="'heroicon-o-'.$sec['icon']" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $sec['title'] }}</h3>
                        <p class="mt-1.5 text-sm text-slate-500">{{ $sec['body'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Compliance chips --}}
            <div class="reveal mt-10 flex flex-wrap items-center justify-center gap-2.5">
                @foreach ([__('SOC 2-ready'), __('PCI-DSS'), __('AML / KYC'), __('2FA'), __('Cold storage'), __('Audited quarterly')] as $chip)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3.5 py-1.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200">
                        <x-heroicon-s-check-circle class="h-3.5 w-3.5" style="color:var(--up)" /> {{ $chip }}
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== FAQ ===================== --}}
    <section id="faq" class="relative px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="mx-auto max-w-3xl">
            <div class="reveal text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.18em]" style="color:var(--brand)">{{ __('FAQ') }}</p>
                <h2 class="mt-3 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">{{ __('Questions,') }} <span class="grad-text">{{ __('answered.') }}</span></h2>
                <p class="mt-4 text-slate-600">{{ __('For more, see our') }} <a href="{{ route('faqs.public') }}" class="font-medium underline-offset-2 hover:underline" style="color:var(--brand)">{{ __('full FAQ') }}</a>.</p>
            </div>

            <div class="reveal mt-10 space-y-3" x-data="{ open: null }">
                @foreach ($faqs as $i => $faq)
                    <div class="glass overflow-hidden rounded-2xl">
                        <button @click="open === {{ $i }} ? open = null : open = {{ $i }}" class="flex w-full items-center justify-between gap-4 p-5 text-left" :aria-expanded="open === {{ $i }}">
                            <span class="text-sm font-semibold text-slate-900">{{ $faq['q'] }}</span>
                            <x-heroicon-o-chevron-down class="h-5 w-5 flex-none text-slate-400 transition-transform duration-300" x-bind:class="open === {{ $i }} ? 'rotate-180' : ''" />
                        </button>
                        <div x-show="open === {{ $i }}" x-cloak
                            x-transition:enter="transition duration-300 ease-out"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0">
                            <p class="px-5 pb-5 text-sm leading-relaxed text-slate-500">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.footer />
</div>

{{-- ===================== Alpine components + progressive enhancement ===================== --}}
<script>
    document.addEventListener('alpine:init', () => {
        // 3D pointer tilt for the hero card
        Alpine.data('ppTilt', () => ({
            rx: 0, ry: 0,
            move(e) {
                const r = e.currentTarget.getBoundingClientRect();
                const px = (e.clientX - r.left) / r.width - 0.5;
                const py = (e.clientY - r.top) / r.height - 0.5;
                this.ry = px * 16;
                this.rx = -py * 16;
            },
            reset() { this.rx = 0; this.ry = 0; },
        }));

    });

    // Scroll reveal — dependency-free IntersectionObserver
    (function () {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const els = document.querySelectorAll('.reveal');
        if (reduce || !('IntersectionObserver' in window)) {
            els.forEach(el => el.classList.add('in'));
            return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
        els.forEach(el => io.observe(el));
    })();

    // Number counters — animate on first view
    (function () {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const nodes = document.querySelectorAll('[data-count]');
        if (!nodes.length) return;
        const fmt = (n, dec) => n.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
        const run = (el) => {
            const target = parseFloat(el.getAttribute('data-count')) || 0;
            const dec = parseInt(el.getAttribute('data-dec') || '0', 10);
            const prefix = el.getAttribute('data-prefix') || '';
            const suffix = el.getAttribute('data-suffix') || '';
            if (reduce) { el.textContent = prefix + fmt(target, dec) + suffix; return; }
            const dur = 1300, start = performance.now();
            const tick = (now) => {
                const t = Math.min((now - start) / dur, 1);
                const eased = 1 - Math.pow(1 - t, 3);
                el.textContent = prefix + fmt(target * eased, dec) + suffix;
                if (t < 1) requestAnimationFrame(tick);
                else el.textContent = prefix + fmt(target, dec) + suffix;
            };
            requestAnimationFrame(tick);
        };
        if (!('IntersectionObserver' in window)) { nodes.forEach(run); return; }
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) { run(entry.target); io.unobserve(entry.target); }
            });
        }, { threshold: 0.6 });
        nodes.forEach(n => io.observe(n));
    })();
</script>
</body>
</html>
