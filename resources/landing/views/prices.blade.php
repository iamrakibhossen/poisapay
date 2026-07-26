<x-landing::layouts.master :title="__('Live Prices')" :description="__('Live crypto prices, refreshed every minute.')">
    <div class="mx-auto max-w-4xl px-4 pb-24 pt-14 sm:px-6 sm:pt-20">
        {{-- Heading --}}
        <div class="mx-auto max-w-xl text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.16em]" style="color:var(--brand)">{{ __('Live prices') }}</p>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('Crypto prices, live in :currency', ['currency' => $base]) }}</h1>
            <p class="mt-3 text-slate-600">{{ __('Real-time reference rates, refreshed every minute. Display only — not a tradable quote.') }}</p>
        </div>

        {{-- Price grid — server-rendered, then refreshed in place from the /rates feed. --}}
        <div class="mt-10 grid gap-4 sm:grid-cols-2" data-price-grid data-rates-url="{{ route('marketing.rates') }}" data-fiat-symbol="{{ $symbol }}">
            @foreach ($coins as $sym => $name)
                <div class="lp-card flex items-center gap-4 p-5">
                    <x-landing::asset-icon :symbol="$sym" size="lg" class="shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-slate-900">{{ $sym }}</p>
                        <p class="truncate text-xs text-slate-400">{{ $name }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="lp-tabular text-lg font-bold text-slate-900" data-price data-sym="{{ $sym }}">{{ $symbol }}{{ number_format((float) ($rates[$sym] ?? 0), 2) }}</p>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ $base }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Live indicator + last-updated stamp --}}
        <p class="mt-6 flex items-center justify-center gap-1.5 text-xs font-medium text-slate-400">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 motion-safe:animate-pulse"></span>
            {{ __('Live · updates every minute') }} · {{ __('Last updated') }}
            <span data-updated>{{ $asOf->timezone(config('app.timezone'))->format('H:i') }}</span>
        </p>

        {{-- CTA --}}
        <div class="mt-12 text-center">
            <a href="{{ route('register') }}"
                class="inline-flex items-center justify-center rounded-full px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                style="background:var(--brand)">
                {{ __('Get started') }}
            </a>
            <p class="mt-3 text-xs text-slate-400">
                {{ __('Rates are indicative reference prices from public market data.') }}
            </p>
        </div>
    </div>

    @push('scripts')
    <style>@keyframes ppFlashP{0%{color:var(--brand)}100%{color:inherit}}.lp-price-flash{animation:ppFlashP .7s ease-out}</style>
    <script>
    (function () {
        var grid = document.querySelector('[data-price-grid]');
        if (!grid || !window.fetch) return;
        var url = grid.getAttribute('data-rates-url');
        var stamp = document.querySelector('[data-updated]');
        var fiat = grid.getAttribute('data-fiat-symbol') || '';
        function fmt(n) { return fiat + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function refresh() {
            if (document.hidden) return;
            fetch(url, { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || !data.rates) return;
                    if (data.symbol) fiat = data.symbol;
                    grid.querySelectorAll('[data-price][data-sym]').forEach(function (el) {
                        var v = data.rates[el.getAttribute('data-sym')];
                        if (v == null) return;
                        var t = fmt(v);
                        if (el.textContent !== t) { el.textContent = t; el.classList.remove('lp-price-flash'); void el.offsetWidth; el.classList.add('lp-price-flash'); }
                    });
                    if (stamp) { stamp.textContent = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }); }
                }).catch(function () {});
        }
        setInterval(refresh, 60000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
    })();
    </script>
    @endpush
</x-landing::layouts.master>
