@props([
    'id' => 'cv',
    'compact' => false,   /* tighter header, hides subtext + footer note */
    'showLive' => true,
])
@php
    // Live crypto→fiat reference rates in the viewer's base currency (signed-in
    // user's choice, else USD); cached ~60s, falls back to indicative values when
    // the feed is down. Refreshed client-side via the rates route.
    $displayCoins = ['USDT', 'USDC', 'ETH', 'BTC', 'BNB', 'TON'];
    $base = \App\Support\BaseCurrency::displayCode();
    $symbol = \App\Support\BaseCurrency::symbol($base);
    $rates = app(\App\Domain\Exchange\CoinGeckoRateProvider::class)->ratesWithFallback($base, $displayCoins);
    $coins = collect($displayCoins)->map(fn ($s) => [$s, $rates[$s]])->all();

    // Initial figures for the default 1,000 units of the first coin (JS recomputes on load).
    // Marketing widget shows NO conversion fee (data-spread="0"); the live product spread
    // is unchanged (see exchange_spread_bps) — this figure is illustrative only.
    $first = $displayCoins[0];
    $r0 = (float) ($rates[$first] ?? 0);
    $gross0 = 1000 * $r0;
    $charge0 = 0;
    $fmt2 = fn ($n) => number_format((float) $n, 2);
@endphp
<div class="lp-converter lp-card relative w-full overflow-hidden p-6 sm:p-7" data-spread="0" data-rates-url="{{ route('marketing.rates') }}" data-fiat-symbol="{{ $symbol }}" style="box-shadow:var(--shadow-pop)">
    <div aria-hidden="true" class="absolute inset-0 -z-10 blur-3xl" style="background:radial-gradient(circle at 70% 25%,rgba(37,99,235,.16),transparent 60%)"></div>
    <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1" style="background:linear-gradient(90deg,var(--brand),var(--brand-600),var(--up))"></div>

    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-[15px] font-bold text-slate-900">{{ __('Spend crypto, pay in :currency', ['currency' => $base]) }}</p>
            @unless ($compact)<p class="mt-0.5 text-xs text-slate-500">{{ __('Your card converts crypto to :currency at checkout', ['currency' => $base]) }}</p>@endunless
        </div>
        @if ($showLive)
            <span class="inline-flex flex-none items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" style="background:rgba(16,185,129,.12);color:var(--up)">
                <span class="h-1.5 w-1.5 rounded-full lp-pulse" style="background:var(--up)"></span> {{ __('Live') }}
            </span>
        @endif
    </div>

    {{-- You spend (funded from your crypto balance) --}}
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white px-4 pb-3.5 pt-3 transition focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-500/20">
        <label for="{{ $id }}-amount" class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('You spend') }}</label>
        <div class="mt-1 flex items-center justify-between gap-3">
            <input id="{{ $id }}-amount" type="text" inputmode="decimal" value="1,000"
                class="cv-amount min-w-0 flex-1 border-0 bg-transparent p-0 text-[1.7rem] font-bold leading-none lp-tabular text-slate-900 focus:outline-none focus:ring-0" />
            <div class="relative flex-none">
                <select aria-label="{{ __('Spend from coin') }}"
                    class="cv-from appearance-none rounded-full border border-slate-200 bg-slate-50 py-2 pl-3.5 pr-9 text-sm font-bold text-slate-900 transition hover:bg-slate-100 focus:border-blue-400 focus:outline-none focus:ring-0">
                    @foreach ($coins as $c)<option value="{{ $c[1] }}" data-sym="{{ $c[0] }}">{{ $c[0] }}</option>@endforeach
                </select>
                <x-heroicon-o-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            </div>
        </div>
    </div>

    {{-- Card glyph divider (decorative) — the card does the conversion --}}
    <div class="relative z-10 -my-3 flex justify-center" aria-hidden="true">
        <span class="grid h-9 w-9 place-items-center rounded-full border-4 border-white text-white shadow-sm" style="background:linear-gradient(120deg,var(--brand),var(--brand-600))">
            <x-heroicon-o-credit-card class="h-4 w-4" />
        </span>
    </div>

    {{-- Your card pays (in fiat) --}}
    <div class="rounded-2xl border border-slate-200 px-4 pb-3.5 pt-3" style="background:linear-gradient(180deg,rgba(37,99,235,.05),rgba(248,250,252,.7))">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Your card pays') }}</p>
        <div class="mt-1 flex items-center justify-between gap-3">
            <p class="cv-result min-w-0 flex-1 truncate text-[1.7rem] font-bold leading-none lp-tabular text-slate-900">{{ $fmt2($gross0 - $charge0) }}</p>
            <span class="inline-flex flex-none items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-900">
                <span class="grid h-5 w-5 place-items-center rounded-full text-xs font-bold text-white" style="background:linear-gradient(120deg,var(--brand),var(--brand-600))">{{ $symbol }}</span> {{ $base }}
            </span>
        </div>
    </div>

    {{-- Reference rate + conversion fee --}}
    <div class="mt-4 grid grid-cols-2 divide-x divide-slate-200 overflow-hidden rounded-xl border border-slate-200 bg-slate-50/60 text-center">
        <div class="px-3 py-2.5">
            <p class="flex items-center justify-center gap-1 text-[11px] text-slate-400"><x-heroicon-o-arrows-right-left class="h-3 w-3" /> {{ __('Reference rate') }}</p>
            <p class="cv-rate mt-0.5 truncate text-xs font-semibold lp-tabular text-slate-700">1 {{ $first }} = {{ $fmt2($r0) }} {{ $symbol }}</p>
        </div>
        <div class="px-3 py-2.5">
            <p class="flex items-center justify-center gap-1 text-[11px] text-slate-400"><x-heroicon-o-receipt-percent class="h-3 w-3" /> {{ __('Conversion fee') }}</p>
            <p class="mt-0.5 text-xs font-bold" style="color:var(--up)">{{ __('Free') }}</p>
        </div>
    </div>

    <a href="{{ route('register') }}" class="lp-btn lp-btn-primary lp-btn-lg mt-5 w-full">{{ __('Get your card') }} <x-heroicon-o-arrow-right class="h-5 w-5" /></a>
    @unless ($compact)
        <p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-slate-400">
            <x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> {{ __('Auto-converted at checkout · reference rate, not a quote') }}
        </p>
    @endunless
</div>
{{-- Behaviour (init + 60s rate refresh) lives in resources/landing/js/landing-converter.js. --}}
