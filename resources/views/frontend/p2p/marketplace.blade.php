<x-layouts.app :title="__('P2P Marketplace')">
    @php
        $buyActive = $want === 'buy';
        $onlineCount = $profiles->where('is_online', true)->count();
        $fixed = collect($ads->items())->filter(fn ($a) => $a->price_type->value === 'fixed');
        $bestPrice = $buyActive
            ? $fixed->min(fn ($a) => (float) $a->fixed_price)
            : $fixed->max(fn ($a) => (float) $a->fixed_price);
        $fiat = optional($ads->first())->fiat_currency ?? 'BDT';
    @endphp

    <div class="space-y-6"
         x-data="{ ad: null, choose(a) { this.ad = a; $dispatch('open-modal', 'p2p-order'); } }">

        {{-- Header --}}
        <x-ui.page-header :title="__('P2P Marketplace')" :subtitle="__('Buy and sell USDT peer-to-peer — every trade is protected by escrow until both sides confirm.')">
            <x-slot:actions>
                <a href="{{ route('p2p.dashboard') }}"><x-ui.button variant="secondary" icon="chart-bar">{{ __('Dashboard') }}</x-ui.button></a>
                <a href="{{ route('p2p.orders') }}"><x-ui.button variant="secondary" icon="clock">{{ __('My orders') }}</x-ui.button></a>
                <a href="{{ route('p2p.ads') }}"><x-ui.button variant="secondary" icon="megaphone">{{ __('My ads') }}</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Market snapshot --}}
        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-200 shadow-[var(--shadow-card)] sm:grid-cols-4">
            <div class="flex items-center gap-3 bg-white p-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $buyActive ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' }}">
                    <x-heroicon-o-tag class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="truncate text-xs font-medium text-neutral-500">{{ $buyActive ? __('Best buy price') : __('Best sell price') }}</p>
                    <p class="text-lg font-bold tabular text-neutral-900">
                        @if ($bestPrice){{ number_format($bestPrice, 2) }} <span class="text-xs font-medium text-neutral-400">{{ $fiat }}</span>@else — @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-white p-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600">
                    <x-heroicon-o-megaphone class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="truncate text-xs font-medium text-neutral-500">{{ __('Live ads') }}</p>
                    <p class="text-lg font-bold tabular text-neutral-900">{{ number_format($ads->total()) }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-white p-4">
                <span class="relative grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-green-50 text-green-600">
                    <x-heroicon-o-user-group class="h-5 w-5" />
                    <span class="absolute right-1.5 top-1.5 flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                    </span>
                </span>
                <div class="min-w-0">
                    <p class="truncate text-xs font-medium text-neutral-500">{{ __('Advertisers online') }}</p>
                    <p class="text-lg font-bold tabular text-neutral-900">{{ number_format($onlineCount) }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-white p-4">
                <x-ui.asset-icon symbol="USDT" size="md" class="shrink-0" />
                <div class="min-w-0">
                    <p class="truncate text-xs font-medium text-neutral-500">{{ __('Asset') }}</p>
                    <p class="text-lg font-bold text-neutral-900">USDT</p>
                </div>
            </div>
        </div>

        {{-- Auto-match / Quick trade — instantly open an order against the best-priced offer --}}
        @if (feature('p2p_auto_match', false))
            <form method="POST" action="{{ route('p2p.match') }}" class="rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50/60 to-white p-4 shadow-[var(--shadow-card)]"
                  x-data="{ side: '{{ $buyActive ? 'buy' : 'sell' }}' }">
                @csrf
                <input type="hidden" name="side" :value="side" />
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-o-bolt class="h-4 w-4" /></span>
                    <div>
                        <p class="text-sm font-semibold text-neutral-900">{{ __('Quick trade') }}</p>
                        <p class="text-xs text-neutral-500">{{ __('We auto-match you to the best-priced offer and open escrow instantly.') }}</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-[auto_1fr_1fr_auto] sm:items-end">
                    {{-- Side --}}
                    <div class="pp-seg self-stretch">
                        <button type="button" @click="side = 'buy'" :class="side === 'buy' ? 'bg-green-600 text-white shadow-sm' : 'text-neutral-500'">{{ __('Buy') }}</button>
                        <button type="button" @click="side = 'sell'" :class="side === 'sell' ? 'bg-red-600 text-white shadow-sm' : 'text-neutral-500'">{{ __('Sell') }}</button>
                    </div>
                    {{-- Amount --}}
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Amount (USDT)') }}</label>
                        <input name="amount" type="number" step="0.01" min="0.01" required placeholder="100.00"
                            class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm tabular focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                    </div>
                    {{-- Payment method (required) — pick one --}}
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Payment method') }}</label>
                        <div class="grid grid-cols-2 gap-1.5">
                            @foreach ($methods as $m)
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_method_id" value="{{ $m->id }}" class="peer sr-only" required />
                                    <span class="block truncate rounded-lg border border-neutral-200 border-l-[3px] border-l-neutral-300 px-2.5 py-2 text-xs font-medium text-neutral-600 transition hover:border-neutral-300 peer-checked:border-brand-500 peer-checked:border-l-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700">{{ $m->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <x-ui.button type="submit" icon="bolt" ::class="side === 'buy' ? '!bg-green-600 hover:!bg-green-700' : '!bg-red-600 hover:!bg-red-700'">
                        <span x-text="side === 'buy' ? '{{ __('Match & Buy') }}' : '{{ __('Match & Sell') }}'"></span>
                    </x-ui.button>
                </div>
            </form>
        @endif

        {{-- Buy / Sell — two separate buttons, centered --}}
        <div class="flex justify-center">
            <div class="inline-flex gap-2 rounded-2xl bg-neutral-100 p-1.5 shadow-inner">
                <a href="{{ route('p2p', ['side' => 'buy']) }}"
                   class="rounded-xl px-10 py-2.5 text-sm font-bold transition {{ $buyActive ? 'bg-green-600 text-white shadow-sm' : 'text-neutral-500 hover:text-neutral-900' }}">{{ __('Buy USDT') }}</a>
                <a href="{{ route('p2p', ['side' => 'sell']) }}"
                   class="rounded-xl px-10 py-2.5 text-sm font-bold transition {{ ! $buyActive ? 'bg-red-600 text-white shadow-sm' : 'text-neutral-500 hover:text-neutral-900' }}">{{ __('Sell USDT') }}</a>
            </div>
        </div>

        {{-- Filter toolbar — same pattern as the transactions page: pill tabs + right-aligned form --}}
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            {{-- Sort pills (query-string links, preserve other filters) --}}
            <div class="-mx-1 flex flex-nowrap gap-1 overflow-x-auto px-1 lg:flex-wrap">
                @foreach (['recommended' => __('Recommended'), 'price' => __('Best price'), 'completion' => __('Completion'), 'fast_release' => __('Fastest'), 'trades' => __('Most trades')] as $val => $label)
                    <a href="{{ route('p2p', array_merge(request()->query(), ['sort' => $val, 'page' => 1])) }}"
                        class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium transition {{ $filters['sort'] === $val ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-800' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('p2p') }}" class="flex flex-wrap gap-2 lg:ml-auto">
                <input type="hidden" name="side" value="{{ $want }}" />
                <input type="hidden" name="sort" value="{{ $filters['sort'] }}" />

                <label class="pp-chip cursor-pointer has-[:checked]:border-green-300 has-[:checked]:bg-green-50 has-[:checked]:text-green-700">
                    <input type="checkbox" name="online" value="1" @checked($filters['online']) onchange="this.form.submit()" class="sr-only">
                    <span class="h-2 w-2 rounded-full bg-green-500"></span> {{ __('Online') }}
                </label>
                <label class="pp-chip cursor-pointer has-[:checked]:border-brand-300 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                    <input type="checkbox" name="verified" value="1" @checked($filters['verified']) onchange="this.form.submit()" class="sr-only">
                    <x-heroicon-s-check-badge class="h-4 w-4 text-brand-500" /> {{ __('Verified') }}
                </label>

                <select name="method" onchange="this.form.submit()" class="pp-input w-36 text-sm">
                    <option value="">{{ __('All payments') }}</option>
                    @foreach ($methods as $m)
                        <option value="{{ $m->id }}" @selected($filters['method'] === $m->id)>{{ $m->name }}</option>
                    @endforeach
                </select>

                <div class="relative flex-1 lg:w-56 lg:flex-none">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Search advertiser…') }}" class="pp-input w-full !pl-10 text-sm" />
                </div>
            </form>
        </div>

        {{-- Offer card grid --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($ads as $ad)
                @php
                    $p = $profiles[$ad->user_id] ?? null;
                    $online = $p->is_online ?? false;
                    $level = $p->level ?? 0;
                    $verified = $level >= 2;
                    $methodsCsv = strtolower($ad->paymentMethods->pluck('name')->implode(','));
                    $completion = number_format(($p->completion_rate_bps ?? 0) / 100, 1);
                @endphp
                <div class="flex flex-col rounded-2xl border border-neutral-200 bg-white p-5 shadow-[var(--shadow-card)] transition-all hover:border-neutral-300 hover:shadow-md">

                    {{-- Advertiser --}}
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <div class="relative shrink-0">
                                <x-ui.avatar :name="$ad->user->name" size="md" />
                                @if ($online)
                                    <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-green-500" title="{{ __('Online') }}"></span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('p2p.merchant', $ad->user_id) }}" class="flex items-center gap-1 truncate text-sm font-semibold text-neutral-900 hover:text-brand-600">
                                    <span class="truncate">{{ $ad->user->name }}</span>
                                    @if ($verified)<x-heroicon-s-check-badge class="h-4 w-4 shrink-0 text-brand-500" title="{{ __('Verified merchant') }}" />@endif
                                </a>
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    {{ $p->trade_count ?? 0 }} {{ __('trades') }} · {{ $completion }}% {{ __('completion') }}
                                    @if (($p ?? null) && $p->isFeatured())<span class="ml-1 inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700"><x-heroicon-s-sparkles class="h-3 w-3" />{{ __('Featured') }}</span>@endif
                                    @if ($ad->is_express)<span class="ml-1 inline-flex items-center gap-0.5 rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700"><x-heroicon-s-bolt class="h-3 w-3" />{{ __('Express') }}</span>@endif
                                </p>
                            </div>
                        </div>
                        <span class="shrink-0 rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase {{ $buyActive ? 'border-green-200 bg-green-100 text-green-700' : 'border-red-200 bg-red-100 text-red-700' }}">
                            {{ $buyActive ? __('Buy') : __('Sell') }}
                        </span>
                    </div>

                    {{-- Price --}}
                    <div class="mb-3 flex items-baseline justify-between gap-2">
                        @if ($ad->price_type->value === 'fixed')
                            <span class="text-sm text-neutral-500">{{ __('Price') }}</span>
                            <span class="text-xl font-bold tabular text-neutral-900">{{ number_format((float) $ad->fixed_price, 2) }}
                                <span class="text-xs font-medium text-neutral-400">{{ $ad->fiat_currency }}</span></span>
                        @else
                            <span class="text-sm text-neutral-500">{{ __('Floating') }}</span>
                            <span class="text-sm font-bold text-neutral-900">{{ __('market') }} {{ $ad->margin_bps >= 0 ? '+' : '' }}{{ number_format($ad->margin_bps / 100, 2) }}%</span>
                        @endif
                    </div>

                    {{-- Available / Limit --}}
                    <div class="mb-4 border-t border-neutral-100 pt-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-neutral-500">{{ __('Available') }}</span>
                            <span class="tabular font-medium text-neutral-700">{{ $ad->availableMoney()->format() }}</span>
                        </div>
                        <div class="mt-1 flex justify-between">
                            <span class="text-neutral-500">{{ __('Limit') }}</span>
                            <span class="tabular text-neutral-700">{{ number_format((float) $ad->min_order, 0) }} – {{ number_format((float) $ad->max_order, 0) }} {{ $ad->fiat_currency }}</span>
                        </div>
                    </div>

                    {{-- Payment --}}
                    <div class="mb-4 flex flex-wrap gap-1.5">
                        @foreach ($ad->paymentMethods as $m)
                            <span class="inline-flex items-center border-l-[3px] bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-600" style="border-left-color: #{{ substr(md5($m->key ?? $m->name), 0, 6) }}">{{ $m->name }}</span>
                        @endforeach
                    </div>

                    {{-- Trade --}}
                    <button type="button"
                        class="mt-auto block w-full rounded-lg px-5 py-2.5 text-center text-sm font-semibold text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 {{ $buyActive ? 'bg-green-600 hover:bg-green-700 focus-visible:ring-green-400' : 'bg-red-600 hover:bg-red-500 focus-visible:ring-red-400' }}"
                        x-on:click="choose({ id: '{{ $ad->id }}', price: '{{ $ad->fixed_price ?? 0 }}', min: '{{ $ad->min_order }}', max: '{{ $ad->max_order }}', avail: '{{ $ad->availableMoney()->toDecimal() }}', sym: '{{ $ad->asset->symbol }}', fiat: '{{ $ad->fiat_currency }}', who: '{{ addslashes($ad->user->name) }}', side: '{{ $want }}', trades: {{ (int) ($p->trade_count ?? 0) }}, completion: '{{ $completion }}', online: {{ $online ? 'true' : 'false' }}, verified: {{ $verified ? 'true' : 'false' }}, window: {{ (int) $ad->payment_window_min }}, methods: {{ Illuminate\Support\Js::from($ad->paymentMethods->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values()) }} })">
                        {{ $buyActive ? __('Buy') : __('Sell') }} USDT
                    </button>
                </div>
            @empty
                <div class="sm:col-span-2 lg:col-span-3">
                    <div class="pp-row p-4">
                        <x-ui.empty-state icon="user-group" :title="__('No ads yet')"
                            :description="__('No :want ads are live right now. Check back soon or post your own to get started.', ['want' => $want])">
                            <x-slot:action>
                                <a href="{{ route('p2p.ads.create') }}"><x-ui.button icon="plus">{{ __('Post an ad') }}</x-ui.button></a>
                            </x-slot:action>
                        </x-ui.empty-state>
                    </div>
                </div>
            @endforelse

        </div>

        {{-- Pagination — prev / next (matches the transactions page) --}}
        @if ($ads->lastPage() > 1)
            <div class="flex items-center justify-between text-sm">
                @if (! $ads->onFirstPage())
                    <a href="{{ $ads->previousPageUrl() }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-gray-50">
                        <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-100 px-3 py-1.5 font-medium text-neutral-300">
                        <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
                    </span>
                @endif
                <span class="text-neutral-500">{{ __('Page :page of :last', ['page' => $ads->currentPage(), 'last' => $ads->lastPage()]) }}</span>
                @if ($ads->hasMorePages())
                    <a href="{{ $ads->nextPageUrl() }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-gray-50">
                        {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-100 px-3 py-1.5 font-medium text-neutral-300">
                        {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
                    </span>
                @endif
            </div>
        @endif

        {{-- Order modal (shared, populated by Alpine) --}}
        <x-ui.modal name="p2p-order" :title="__('Place order')" maxWidth="sm">
            <form method="POST" action="{{ route('p2p.orders.store') }}" class="space-y-4"
                x-data="{
                    amount: '',
                    feeBps: {{ (int) getSetting('p2p_taker_fee_bps', (int) config('p2p.taker_fee_bps', 0)) }},
                    get fiatTotal() { return this.amount && this.ad ? Number(this.amount) * Number(this.ad.price) : 0; },
                    get fee() { return this.amount > 0 ? Number(this.amount) * this.feeBps / 10000 : 0; },
                    get net() { return this.amount > 0 ? Number(this.amount) - this.fee : 0; },
                    get valid() { return this.amount > 0; },
                    setPct(p) { if (this.ad) this.amount = (Number(this.ad.avail) * p).toFixed(2).replace(/\.?0+$/, ''); },
                }">
                @csrf
                <input type="hidden" name="ad_id" :value="ad?.id">

                {{-- Advertiser trust header --}}
                <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3">
                    <span class="relative grid h-10 w-10 shrink-0 place-items-center rounded-full text-sm font-bold text-white"
                          :class="ad?.side === 'buy' ? 'bg-green-600' : 'bg-red-600'"
                          x-text="(ad?.who || '?').slice(0,1).toUpperCase()">
                        <span x-show="ad?.online" x-cloak class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-green-500"></span>
                    </span>
                    <div class="min-w-0 text-sm">
                        <p class="flex items-center gap-1 truncate font-semibold text-neutral-900">
                            <span class="truncate" x-text="ad?.who"></span>
                            <template x-if="ad?.verified"><x-heroicon-s-check-badge class="h-4 w-4 shrink-0 text-brand-500" /></template>
                        </p>
                        <p class="text-xs text-neutral-500"><span x-text="ad?.trades ?? 0"></span> {{ __('trades') }} · <span x-text="ad?.completion ?? 0"></span>% {{ __('completion') }}</p>
                    </div>
                    <div class="ml-auto text-right">
                        <p class="tabular text-base font-bold text-neutral-900" x-text="Number(ad?.price).toLocaleString()"></p>
                        <p class="text-[11px] text-neutral-400"><span x-text="ad?.fiat"></span> / <span x-text="ad?.sym"></span></p>
                    </div>
                </div>

                {{-- Amount + quick fill --}}
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="pp-label !mb-0">{{ __('Amount') }} (<span x-text="ad?.sym"></span>)</label>
                        <span class="text-[11px] text-neutral-400">{{ __('Limit') }} <span class="tabular" x-text="Number(ad?.min).toLocaleString()"></span>–<span class="tabular" x-text="Number(ad?.max).toLocaleString()"></span></span>
                    </div>
                    <div class="relative">
                        <input type="text" name="amount" inputmode="decimal" x-model="amount" placeholder="0.00" class="pp-input pr-16 text-lg font-semibold" required>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-neutral-400" x-text="ad?.sym"></span>
                    </div>
                    <div class="mt-2 flex gap-1.5">
                        <template x-for="p in [0.25, 0.5, 0.75, 1]" :key="p">
                            <button type="button" @click="setPct(p)" class="flex-1 rounded-md border border-neutral-200 py-1 text-xs font-medium text-neutral-500 transition hover:border-brand-300 hover:text-brand-600" x-text="p === 1 ? '{{ __('Max') }}' : (p * 100) + '%'"></button>
                        </template>
                    </div>
                </div>

                {{-- Payment method (required) — one row each --}}
                <div>
                    <label class="pp-label">{{ __('Payment method') }}</label>
                    <div class="space-y-2">
                        <template x-for="m in (ad?.methods || [])" :key="m.id">
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 transition hover:border-neutral-300 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">
                                <input type="radio" name="payment_method_id" :value="m.id" required class="h-4 w-4 shrink-0 border-neutral-300 text-brand-600 focus:ring-brand-500" />
                                <span class="truncate text-sm font-medium text-neutral-700" x-text="m.name"></span>
                            </label>
                        </template>
                    </div>
                    <p x-show="ad && (!ad.methods || !ad.methods.length)" x-cloak class="mt-1 text-xs text-amber-600">{{ __('This advertiser hasn’t listed a payment method.') }}</p>
                </div>

                {{-- Live summary --}}
                <div class="space-y-1.5 rounded-xl bg-neutral-50 px-3 py-2.5 text-sm">
                    <div class="flex items-center justify-between text-neutral-500">
                        <span x-text="ad?.side === 'buy' ? '{{ __('You pay') }}' : '{{ __('You receive') }}'"></span>
                        <span class="tabular font-semibold text-neutral-900"><span x-text="fiatTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span> <span class="text-xs font-medium text-neutral-400" x-text="ad?.fiat"></span></span>
                    </div>
                    <div class="flex items-center justify-between text-neutral-500" x-show="feeBps > 0" x-cloak>
                        <span>{{ __('Platform fee') }} (<span x-text="(feeBps / 100)"></span>%)</span>
                        <span class="tabular text-neutral-600">−<span x-text="fee.toLocaleString(undefined, {maximumFractionDigits: 6})"></span> <span class="text-xs text-neutral-400" x-text="ad?.sym"></span></span>
                    </div>
                    <div class="flex items-center justify-between border-t border-neutral-200 pt-1.5" x-show="feeBps > 0 && amount" x-cloak>
                        <span class="font-medium text-neutral-700" x-text="ad?.side === 'buy' ? '{{ __('You receive') }}' : '{{ __('Buyer receives') }}'"></span>
                        <span class="tabular text-base font-bold text-neutral-900"><span x-text="net.toLocaleString(undefined, {maximumFractionDigits: 6})"></span> <span class="text-xs font-medium text-neutral-400" x-text="ad?.sym"></span></span>
                    </div>
                </div>

                <x-ui.button type="submit" class="w-full" x-bind:disabled="!valid" :variant="$buyActive ? 'success' : 'danger'">
                    <span x-text="(ad?.side === 'buy' ? '{{ __('Buy') }}' : '{{ __('Sell') }}') + (amount ? ' ' + amount + ' ' + (ad?.sym || '') : '')"></span> · {{ __('lock escrow') }}
                </x-ui.button>
                <p class="flex items-center justify-center gap-1.5 text-center text-[11px] text-neutral-400">
                    <x-heroicon-s-lock-closed class="h-3.5 w-3.5 text-emerald-500" /> {{ __('Escrow-protected. Pay within') }} <span x-text="ad?.window ?? 15"></span> {{ __('min, then confirm.') }}
                </p>
            </form>
        </x-ui.modal>
    </div>
</x-layouts.app>
