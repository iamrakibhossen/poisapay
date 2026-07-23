@props([
    'id' => 'cv',
    'compact' => false,   /* tighter header, hides subtext + footer note */
    'showLive' => true,
])
@php
    // Live crypto→BDT reference rates (cached ~60s; falls back to indicative
    // values when the feed is down). Refreshed client-side via the rates route.
    $displayCoins = ['USDT', 'USDC', 'ETH', 'BTC', 'BNB', 'TON'];
    $rates = app(\App\Domain\Exchange\CoinGeckoRateProvider::class)->bdtRatesWithFallback($displayCoins);
    $coins = collect($displayCoins)->map(fn ($s) => [$s, $rates[$s]])->all();
@endphp
<div class="pp-converter glass-card relative w-full p-6" data-spread="0.005" data-rates-url="{{ route('marketing.rates') }}" style="box-shadow:var(--shadow-pop)">
    <div aria-hidden="true" class="absolute inset-0 -z-10 blur-3xl" style="background:radial-gradient(circle at 70% 30%,rgba(37,99,235,.14),transparent 65%)"></div>

    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-bold text-slate-900">Convert crypto to Taka</p>
            @unless ($compact)<p class="mt-0.5 text-xs text-slate-500">Live reference rate · settles in seconds</p>@endunless
        </div>
        @if ($showLive)
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" style="background:rgba(16,185,129,.12);color:var(--up)">
                <span class="h-1.5 w-1.5 rounded-full pp-pulse" style="background:var(--up)"></span> Live
            </span>
        @endif
    </div>

    {{-- You swap --}}
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white transition focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-500/25">
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
                <label for="{{ $id }}-amount" class="block text-xs text-slate-400">You swap</label>
                <input id="{{ $id }}-amount" type="text" inputmode="decimal" value="1,000"
                    class="cv-amount w-full border-0 bg-transparent p-0 text-2xl font-bold tabular text-slate-900 focus:outline-none focus:ring-0" />
            </div>
            <div class="relative flex-none">
                <select aria-label="Swap from coin"
                    class="cv-from appearance-none rounded-xl border border-slate-200 bg-slate-50 py-2 pl-3 pr-8 text-sm font-semibold text-slate-900 focus:border-blue-400 focus:outline-none focus:ring-0">
                    @foreach ($coins as $c)<option value="{{ $c[1] }}" data-sym="{{ $c[0] }}">{{ $c[0] }}</option>@endforeach
                </select>
                <x-heroicon-o-chevron-down class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            </div>
        </div>
    </div>

    {{-- Charge + reference-rate connector --}}
    <div class="relative py-1 pl-2">
        <div aria-hidden="true" class="absolute left-[0.9rem] top-2 bottom-2 w-px bg-slate-200"></div>
        <ul class="space-y-1.5 py-1.5 text-xs text-slate-500">
            <li class="flex items-center gap-2">
                <span class="z-10 grid h-4 w-4 place-items-center rounded-full bg-slate-100"><x-heroicon-o-receipt-percent class="h-2.5 w-2.5 text-slate-500" /></span>
                Exchange charge (0.5%) <span class="cv-charge ml-auto font-semibold text-slate-700 tabular">607.50 ৳</span>
            </li>
            <li class="flex items-center gap-2">
                <span class="z-10 grid h-4 w-4 place-items-center rounded-full bg-slate-100"><x-heroicon-o-arrows-right-left class="h-2.5 w-2.5 text-slate-500" /></span>
                <span class="cv-rate font-semibold text-slate-700">1 USDT = 121.50 ৳</span> reference rate
            </li>
        </ul>
    </div>

    {{-- You receive --}}
    <div class="rounded-2xl border border-slate-200 bg-slate-50/70">
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs text-slate-400">You receive</p>
                <p class="cv-result truncate text-2xl font-bold tabular text-slate-900">120,892.50</p>
            </div>
            <span class="inline-flex flex-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900">
                <span class="grid h-5 w-5 place-items-center rounded-full text-xs font-bold text-white" style="background:linear-gradient(120deg,var(--brand),var(--brand-600))">৳</span> BDT
            </span>
        </div>
    </div>

    <a href="{{ route('register') }}" class="pp-btn pp-btn-primary pp-btn-lg mt-5 w-full">Start swapping <x-heroicon-o-arrow-right class="h-5 w-5" /></a>
    @unless ($compact)
        <p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-slate-400">
            <x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> Custodial · reference rate, not a quote
        </p>
    @endunless
</div>

@once
<script>
(function () {
    function init() {
        document.querySelectorAll('.pp-converter:not([data-ready])').forEach(function (root) {
            root.setAttribute('data-ready', '1');
            var amt = root.querySelector('.cv-amount'),
                from = root.querySelector('.cv-from'),
                rateEl = root.querySelector('.cv-rate'),
                chargeEl = root.querySelector('.cv-charge'),
                out = root.querySelector('.cv-result'),
                spread = parseFloat(root.getAttribute('data-spread')) || 0;
            if (!amt || !from || !out) return;
            function fmt(n, d) { return n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }); }
            function calc() {
                var rate = parseFloat(from.value) || 0,
                    raw = parseFloat((amt.value || '').replace(/[^0-9.]/g, '')) || 0,
                    sym = from.options[from.selectedIndex].text,
                    gross = raw * rate, charge = gross * spread, net = gross - charge;
                out.textContent = fmt(net, 2);
                if (rateEl) rateEl.textContent = '1 ' + sym + ' = ' + fmt(rate, 2) + ' ৳';
                if (chargeEl) chargeEl.textContent = fmt(charge, 2) + ' ৳';
            }
            amt.addEventListener('input', calc);
            from.addEventListener('change', calc);
            amt.addEventListener('blur', function () {
                var r = parseFloat((amt.value || '').replace(/[^0-9.]/g, ''));
                if (!isNaN(r)) amt.value = r.toLocaleString('en-US');
            });
            root._ppRecalc = calc;
            calc();
        });
    }

    // Pull fresh crypto→BDT rates and update each converter's option values.
    function refresh() {
        document.querySelectorAll('.pp-converter[data-rates-url]').forEach(function (root) {
            fetch(root.getAttribute('data-rates-url'), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || !data.rates) return;
                    var from = root.querySelector('.cv-from');
                    if (!from) return;
                    Array.prototype.forEach.call(from.options, function (opt) {
                        var sym = opt.getAttribute('data-sym') || opt.text;
                        if (data.rates[sym] != null) opt.value = data.rates[sym];
                    });
                    if (typeof root._ppRecalc === 'function') root._ppRecalc();
                })
                .catch(function () {});
        });
    }

    function start() { init(); setInterval(refresh, 60000); }
    if (document.readyState !== 'loading') start(); else document.addEventListener('DOMContentLoaded', start);
})();
</script>
@endonce
