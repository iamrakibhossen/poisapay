@php
    use App\Models\Chain;
    $chains = Chain::where('is_active', true)->orderBy('name')->get();
    $features = [
        ['icon' => 'wallet', 'title' => 'Multi-chain wallet', 'body' => 'Hold crypto and Taka side by side. Ethereum, BNB Chain and Tron, all in one place.'],
        ['icon' => 'paper-airplane', 'title' => 'Instant P2P', 'body' => 'Send money to any PoisaPay user by handle, email or phone — instantly, with zero fees.'],
        ['icon' => 'arrows-right-left', 'title' => 'Built-in exchange', 'body' => 'Swap between crypto and fiat at a live reference rate with transparent spreads.'],
        ['icon' => 'credit-card', 'title' => 'Virtual cards', 'body' => 'Spend your balance anywhere with virtual cards, gated behind full verification.'],
        ['icon' => 'shield-check', 'title' => 'Bank-grade security', 'body' => 'Custodial cold storage, 2FA, and KYC/AML gating protect every account.'],
        ['icon' => 'gift', 'title' => 'Rewards & referrals', 'body' => 'Earn as you go and invite friends to grow your rewards together.'],
    ];
    $steps = [
        ['icon' => 'identification', 'title' => 'Create & verify', 'body' => 'Sign up in minutes and complete KYC once to unlock the full wallet.'],
        ['icon' => 'arrow-down-tray', 'title' => 'Fund your wallet', 'body' => 'Deposit crypto on-chain or top up with Taka to get an instant balance.'],
        ['icon' => 'rocket-launch', 'title' => 'Move money freely', 'body' => 'Send, swap, spend and earn — across chains and currencies, 24/7.'],
    ];
    $trust = [
        ['icon' => 'lock-closed', 'title' => 'Custodial cold storage', 'body' => 'The vast majority of funds are held offline in segregated custody, isolated from hot infrastructure.'],
        ['icon' => 'finger-print', 'title' => 'Two-factor everywhere', 'body' => 'App-based 2FA, device management and session controls guard every sensitive action.'],
        ['icon' => 'document-check', 'title' => 'KYC/AML gated', 'body' => 'Identity verification and transaction monitoring keep the platform compliant and clean.'],
        ['icon' => 'eye', 'title' => 'Transparent by design', 'body' => 'A double-entry ledger tracks every unit of value with exact, auditable precision.'],
    ];
    $faqs = [
        ['q' => 'Is PoisaPay available in Bangladesh?', 'a' => 'Yes — PoisaPay is built for Bangladesh, with native Taka support alongside crypto and instant local P2P transfers.'],
        ['q' => 'Do I need to complete KYC?', 'a' => 'Basic features work right away. Full verification unlocks higher limits, virtual cards and fiat cash-out, and keeps your account compliant.'],
        ['q' => 'Which networks are supported?', 'a' => 'Ethereum, BNB Smart Chain and Tron today, with Tron-first custody for fast, low-cost transfers. More chains are on the roadmap.'],
        ['q' => 'What does it cost to send money?', 'a' => 'P2P transfers between PoisaPay users are free. On-chain withdrawals and exchange carry transparent network and spread fees shown before you confirm.'],
        ['q' => 'How are my funds secured?', 'a' => 'Funds sit in custodial cold storage with 2FA, device controls and continuous monitoring. Every balance is tracked on an exact double-entry ledger.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PoisaPay · The multi-chain wallet built for Bangladesh</title>
    <meta name="description" content="Hold, send and exchange crypto and Taka in one app. Instant fee-free P2P, virtual cards and bank-grade custody — built for Bangladesh.">
    @vite(['resources/css/app.css', 'resources/js/frontend.js'])
    @include('partials.brand-colors')
    <style>
        [x-cloak]{display:none!important}
        [id]{scroll-margin-top:5rem}
    </style>
</head>
<body class="h-full">
<div class="min-h-full">
    {{-- Top nav --}}
    <header class="sticky top-0 z-40 border-b border-neutral-200/70 bg-neutral-50/80 backdrop-blur">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500 text-ink-900"><x-heroicon-s-bolt class="h-5 w-5" /></span>
                <span class="text-lg font-bold text-neutral-900">PoisaPay</span>
            </a>
            <div class="hidden items-center gap-8 text-sm font-medium text-neutral-600 md:flex">
                <a href="#features" class="transition hover:text-neutral-900">Features</a>
                <a href="#how" class="transition hover:text-neutral-900">How it works</a>
                <a href="#security" class="transition hover:text-neutral-900">Security</a>
                <a href="#faq" class="transition hover:text-neutral-900">FAQ</a>
            </div>
            <div class="flex items-center gap-2">
                @auth
                    <x-ui.button href="{{ route('dashboard') }}" size="sm">Dashboard</x-ui.button>
                @else
                    <x-ui.button href="{{ route('login') }}" variant="ghost" size="sm">Log in</x-ui.button>
                    <x-ui.button href="{{ route('register') }}" size="sm">Get started</x-ui.button>
                @endauth
            </div>
        </nav>
    </header>

    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-brand-50/60 to-transparent"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -top-24 right-0 h-96 w-96 rounded-full bg-brand-200/40 blur-3xl"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:gap-8 lg:px-8 lg:py-24">
            {{-- Copy --}}
            <div class="text-center lg:text-left">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-amber-800 ring-1 ring-inset ring-brand-200">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span> Now live · Tron-first custody
                </span>
                <h1 class="mx-auto mt-6 max-w-2xl text-4xl font-bold tracking-tight text-neutral-900 sm:text-5xl lg:mx-0 lg:text-6xl">
                    The multi-chain wallet built for <span class="text-amber-600">Bangladesh</span>.
                </h1>
                <p class="mx-auto mt-5 max-w-xl text-lg text-neutral-600 lg:mx-0">
                    Hold, send and exchange crypto and Taka. Instant P2P, virtual cards, and bank-grade custody — all in one app.
                </p>
                <div class="mt-9 flex flex-wrap items-center justify-center gap-3 lg:justify-start">
                    <x-ui.button href="{{ route('register') }}" size="lg" iconRight="arrow-right">Create your account</x-ui.button>
                    <x-ui.button href="{{ route('login') }}" variant="secondary" size="lg">Sign in</x-ui.button>
                </div>
                <div class="mt-10 flex flex-wrap items-center justify-center gap-x-10 gap-y-4 text-sm text-neutral-500 lg:justify-start">
                    <div><p class="text-2xl font-bold text-neutral-900">{{ $chains->count() ?: 3 }}</p><p>Chains supported</p></div>
                    <div class="h-8 w-px bg-neutral-200"></div>
                    <div><p class="text-2xl font-bold text-neutral-900">0 ৳</p><p>P2P transfer fee</p></div>
                    <div class="h-8 w-px bg-neutral-200"></div>
                    <div><p class="text-2xl font-bold text-neutral-900">24/7</p><p>Instant settlement</p></div>
                </div>
            </div>

            {{-- Live converter (Wise-style) --}}
            <div class="relative mx-auto w-full max-w-sm lg:max-w-md">
                <div class="pp-card relative p-6 shadow-[var(--shadow-pop)]">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-neutral-900">Convert crypto to Taka</p>
                            <p class="mt-0.5 text-xs text-neutral-500">Live reference rate · settles in seconds</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-money-up/10 px-2.5 py-1 text-xs font-medium text-money-up">
                            <span class="h-1.5 w-1.5 rounded-full bg-money-up"></span> Live
                        </span>
                    </div>

                    {{-- You send --}}
                    <div class="mt-5 rounded-xl border border-neutral-200 transition focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-500/30">
                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <label for="cv-amount" class="block text-xs text-neutral-400">You send</label>
                                <input id="cv-amount" type="text" inputmode="decimal" value="1,000"
                                    class="w-full border-0 bg-transparent p-0 text-2xl font-bold tabular text-neutral-900 focus:outline-none focus:ring-0" />
                            </div>
                            <div class="relative flex-none">
                                <select id="cv-from" aria-label="Send currency"
                                    class="appearance-none rounded-lg border border-neutral-200 bg-neutral-50 py-2 pl-3 pr-8 text-sm font-semibold text-neutral-900 focus:border-brand-400 focus:outline-none focus:ring-0">
                                    <option value="121.50">USDT</option>
                                    <option value="121.45">USDC</option>
                                    <option value="402150">ETH</option>
                                </select>
                                <x-heroicon-o-chevron-down class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                            </div>
                        </div>
                    </div>

                    {{-- Rate / fee connector --}}
                    <div class="relative py-1 pl-2">
                        <div aria-hidden="true" class="absolute left-[0.9rem] top-2 bottom-2 w-px bg-neutral-200"></div>
                        <ul class="space-y-1.5 py-1.5 text-xs text-neutral-500">
                            <li class="flex items-center gap-2">
                                <span class="z-10 grid h-4 w-4 place-items-center rounded-full bg-money-up/15"><span class="h-1.5 w-1.5 rounded-full bg-money-up"></span></span>
                                <span class="font-semibold text-money-up">0 ৳</span> PoisaPay transfer fee
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="z-10 grid h-4 w-4 place-items-center rounded-full bg-neutral-100"><x-heroicon-o-arrows-right-left class="h-2.5 w-2.5 text-neutral-500" /></span>
                                <span id="cv-rate" class="font-semibold text-neutral-700">1 USDT = 121.50 ৳</span> reference rate
                            </li>
                        </ul>
                    </div>

                    {{-- Recipient gets --}}
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50/60">
                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-neutral-400">Recipient gets</p>
                                <p id="cv-result" class="truncate text-2xl font-bold tabular text-neutral-900">121,500.00</p>
                            </div>
                            <span class="inline-flex flex-none items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-semibold text-neutral-900">
                                <span class="grid h-5 w-5 place-items-center rounded-full bg-brand-500 text-xs font-bold text-ink-900">৳</span> BDT
                            </span>
                        </div>
                    </div>

                    <a href="{{ route('register') }}" class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-base font-semibold text-ink-900 transition hover:bg-brand-400">
                        Get started <x-heroicon-o-arrow-right class="h-5 w-5" />
                    </a>
                    <p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-neutral-400">
                        <x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> Custodial · KYC/AML gated
                    </p>
                </div>
                <div aria-hidden="true" class="absolute -bottom-4 -right-4 -z-10 h-full w-full rounded-[var(--radius-card)] bg-brand-100/60"></div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">Features</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight text-neutral-900">Everything you need to move money</h2>
            <p class="mt-3 text-neutral-600">One account for crypto, Taka, transfers, exchange and cards.</p>
        </div>
        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($features as $f)
                <div class="pp-card p-6 transition duration-200 hover:-translate-y-1 hover:border-brand-200 hover:shadow-[var(--shadow-pop)]">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-600">
                        <x-dynamic-component :component="'heroicon-o-'.$f['icon']" class="h-6 w-6" />
                    </span>
                    <h3 class="mt-4 text-base font-semibold text-neutral-900">{{ $f['title'] }}</h3>
                    <p class="mt-1.5 text-sm text-neutral-500">{{ $f['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section id="how" class="border-y border-neutral-200/70 bg-neutral-50/60">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">How it works</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-neutral-900">Up and running in three steps</h2>
                <p class="mt-3 text-neutral-600">From sign-up to your first transfer in minutes — no bank branch required.</p>
            </div>
            <div class="mt-12 grid gap-5 md:grid-cols-3">
                @foreach ($steps as $i => $s)
                    <div class="relative pp-card p-6">
                        <span class="absolute right-6 top-6 text-5xl font-bold text-neutral-100">{{ $i + 1 }}</span>
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-500 text-ink-900">
                            <x-dynamic-component :component="'heroicon-o-'.$s['icon']" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-neutral-900">{{ $s['title'] }}</h3>
                        <p class="mt-1.5 text-sm text-neutral-500">{{ $s['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Chains supported --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="pp-card p-8 text-center">
            <p class="text-sm font-medium text-neutral-500">Supported networks</p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-4">
                @forelse ($chains as $chain)
                    <span class="inline-flex items-center gap-2.5 rounded-xl border border-neutral-200 px-4 py-2.5 transition hover:border-brand-200 hover:bg-brand-50/40">
                        <x-ui.asset-icon :symbol="$chain->native_symbol ?? $chain->key->nativeSymbol()" size="md" />
                        <span class="text-sm font-semibold text-neutral-900">{{ $chain->name }}</span>
                    </span>
                @empty
                    @foreach (['Ethereum', 'BNB Smart Chain', 'Tron'] as $name)
                        <span class="inline-flex items-center rounded-xl border border-neutral-200 px-4 py-2.5 text-sm font-semibold text-neutral-900">{{ $name }}</span>
                    @endforeach
                @endforelse
            </div>
        </div>
    </section>

    {{-- Security & trust --}}
    <section id="security" class="border-y border-neutral-200/70 bg-neutral-50/60">
        <div class="mx-auto grid max-w-7xl items-start gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">Security &amp; trust</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-neutral-900">Your money, seriously protected</h2>
                <p class="mt-4 text-neutral-600">
                    PoisaPay is custodial and compliance-first. Funds are held in cold storage, every action is guarded by 2FA,
                    and an exact double-entry ledger accounts for every unit of value on the platform.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <span class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700"><x-heroicon-s-shield-check class="h-4 w-4 text-money-up" /> KYC/AML gated</span>
                    <span class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700"><x-heroicon-s-lock-closed class="h-4 w-4 text-money-up" /> Cold storage</span>
                    <span class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700"><x-heroicon-s-check-badge class="h-4 w-4 text-money-up" /> Exact ledger</span>
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ($trust as $t)
                    <div class="pp-card p-5">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-brand-600">
                            <x-dynamic-component :component="'heroicon-o-'.$t['icon']" class="h-5 w-5" />
                        </span>
                        <h3 class="mt-4 text-sm font-semibold text-neutral-900">{{ $t['title'] }}</h3>
                        <p class="mt-1.5 text-sm text-neutral-500">{{ $t['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="text-center">
            <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">FAQ</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight text-neutral-900">Frequently asked questions</h2>
            <p class="mt-3 text-neutral-600">The essentials. For more, see our <a href="{{ route('faqs.public') }}" class="font-medium text-amber-600 underline-offset-2 hover:underline">full FAQ</a>.</p>
        </div>
        <div class="mt-10 space-y-3">
            @foreach ($faqs as $faq)
                <details class="group pp-card overflow-hidden">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 text-sm font-semibold text-neutral-900 marker:hidden">
                        {{ $faq['q'] }}
                        <x-heroicon-o-chevron-down class="h-5 w-5 flex-none text-neutral-400 transition group-open:rotate-180" />
                    </summary>
                    <p class="px-5 pb-5 text-sm text-neutral-500">{{ $faq['a'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-neutral-200/70 bg-neutral-50/60">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500 text-ink-900"><x-heroicon-s-bolt class="h-5 w-5" /></span>
                        <span class="text-lg font-bold text-neutral-900">PoisaPay</span>
                    </a>
                    <p class="mt-4 max-w-xs text-sm text-neutral-500">The multi-chain wallet built for Bangladesh. Hold, send and exchange crypto and Taka in one app.</p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-neutral-900">Product</p>
                    <ul class="mt-3 space-y-2.5 text-sm text-neutral-500">
                        <li><a href="#features" class="transition hover:text-neutral-900">Features</a></li>
                        <li><a href="#how" class="transition hover:text-neutral-900">How it works</a></li>
                        <li><a href="#security" class="transition hover:text-neutral-900">Security</a></li>
                        <li><a href="{{ route('register') }}" class="transition hover:text-neutral-900">Get started</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-sm font-semibold text-neutral-900">Support</p>
                    <ul class="mt-3 space-y-2.5 text-sm text-neutral-500">
                        <li><a href="#faq" class="transition hover:text-neutral-900">FAQ</a></li>
                        <li><a href="{{ route('faqs.public') }}" class="transition hover:text-neutral-900">Help center</a></li>
                        <li><a href="{{ route('login') }}" class="transition hover:text-neutral-900">Log in</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-sm font-semibold text-neutral-900">Account</p>
                    <ul class="mt-3 space-y-2.5 text-sm text-neutral-500">
                        @auth
                            <li><a href="{{ route('dashboard') }}" class="transition hover:text-neutral-900">Dashboard</a></li>
                        @else
                            <li><a href="{{ route('register') }}" class="transition hover:text-neutral-900">Create account</a></li>
                            <li><a href="{{ route('login') }}" class="transition hover:text-neutral-900">Sign in</a></li>
                        @endauth
                    </ul>
                </div>
            </div>
            <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-neutral-200/70 pt-6 text-sm text-neutral-500 sm:flex-row">
                <p>Custodial · KYC/AML gated · © {{ date('Y') }} PoisaPay</p>
                <p>Built for Bangladesh 🇧🇩</p>
            </div>
        </div>
    </footer>
</div>

{{-- Hero converter: live reference-rate calculation (progressive enhancement) --}}
<script>
(function () {
    var amt = document.getElementById('cv-amount');
    var from = document.getElementById('cv-from');
    var rateEl = document.getElementById('cv-rate');
    var out = document.getElementById('cv-result');
    if (!amt || !from || !rateEl || !out) return;

    function fmt(n, dec) {
        return n.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    }
    function calc() {
        var rate = parseFloat(from.value) || 0;
        var raw = parseFloat((amt.value || '').replace(/[^0-9.]/g, '')) || 0;
        var sym = from.options[from.selectedIndex].text;
        out.textContent = fmt(raw * rate, 2);
        rateEl.textContent = '1 ' + sym + ' = ' + fmt(rate, 2) + ' ৳';
    }
    amt.addEventListener('input', calc);
    from.addEventListener('change', calc);
    amt.addEventListener('blur', function () {
        var raw = parseFloat((amt.value || '').replace(/[^0-9.]/g, ''));
        if (!isNaN(raw)) amt.value = raw.toLocaleString('en-US');
    });
    calc();
})();
</script>
</body>
</html>
