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
         x-data="{
            ad: null,
            search: '', method: '', online: false, verified: false, dir: 'default', shown: 0,
            choose(a) { this.ad = a; $dispatch('open-modal', 'p2p-order'); },
            visible(el) {
                const d = el.dataset;
                if (this.search && !d.name.includes(this.search.toLowerCase().trim())) return false;
                if (this.method && !d.methods.split(',').includes(this.method)) return false;
                if (this.online && d.online !== '1') return false;
                if (this.verified && d.verified !== '1') return false;
                return true;
            },
            order(el) {
                const p = Math.round((parseFloat(el.dataset.price) || 0) * 100);
                return this.dir === 'asc' ? p : this.dir === 'desc' ? -p : 0;
            },
         }"
         x-effect="shown = [...$root.querySelectorAll('[data-adrow]')].filter(el => visible(el)).length">

        {{-- Header --}}
        <x-ui.page-header :title="__('P2P Marketplace')" :subtitle="__('Buy and sell USDT peer-to-peer — every trade is protected by escrow until both sides confirm.')">
            <x-slot:actions>
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

        {{-- Buy / Sell + sticky filter toolbar --}}
        <div class="pp-toolbar sticky top-4 z-20 space-y-4 p-4">
            {{-- Row 1: side switch + live count --}}
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="pp-seg">
                    <a href="{{ route('p2p', ['side' => 'buy']) }}"
                       class="{{ $buyActive ? 'bg-green-600 text-white shadow-sm' : 'text-neutral-500 hover:text-neutral-900' }}">{{ __('Buy USDT') }}</a>
                    <a href="{{ route('p2p', ['side' => 'sell']) }}"
                       class="{{ ! $buyActive ? 'bg-red-600 text-white shadow-sm' : 'text-neutral-500 hover:text-neutral-900' }}">{{ __('Sell USDT') }}</a>
                </div>

                <p class="flex items-center gap-1.5 text-sm text-neutral-500">
                    <x-heroicon-o-funnel class="h-4 w-4 text-neutral-400" />
                    {{ __('Showing') }} <span class="font-semibold tabular text-neutral-900" x-text="shown"></span>
                    {{ __('of') }} <span class="tabular">{{ $ads->count() }}</span>
                </p>
            </div>

            {{-- Row 2: search + filters --}}
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                {{-- Search --}}
                <div class="relative flex-1">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                    <input type="text" x-model="search" placeholder="{{ __('Search advertiser…') }}" class="pp-input h-10 w-full min-h-0 py-0 pl-9 pr-9 text-sm">
                    <button type="button" x-show="search" x-cloak @click="search=''" aria-label="{{ __('Clear search') }}"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-700">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:flex sm:items-center">
                    {{-- Payment method --}}
                    <div class="relative">
                        <x-heroicon-o-credit-card class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                        <select x-model="method" class="pp-input h-10 w-full min-h-0 py-0 pl-9 pr-8 text-sm sm:w-auto">
                            <option value="">{{ __('All payments') }}</option>
                            @foreach ($methods as $m)
                                <option value="{{ strtolower($m->name) }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Price sort --}}
                    <div class="relative">
                        <x-heroicon-o-arrows-up-down class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                        <select x-model="dir" class="pp-input h-10 w-full min-h-0 py-0 pl-9 pr-8 text-sm sm:w-auto">
                            <option value="default">{{ __('Recommended') }}</option>
                            <option value="asc">{{ __('Price: low → high') }}</option>
                            <option value="desc">{{ __('Price: high → low') }}</option>
                        </select>
                    </div>
                </div>

                {{-- Toggles --}}
                <div class="flex items-center gap-2">
                    <button type="button" class="pp-chip flex-1 justify-center sm:flex-none" :class="{ 'is-on': online }" @click="online = !online">
                        <span class="h-2 w-2 rounded-full bg-green-500"></span> {{ __('Online') }}
                    </button>
                    <button type="button" class="pp-chip flex-1 justify-center sm:flex-none" :class="{ 'is-on': verified }" @click="verified = !verified">
                        <x-heroicon-s-check-badge class="h-4 w-4 text-brand-500" /> {{ __('Verified') }}
                    </button>
                </div>
            </div>

            {{-- Active filter feedback --}}
            <div x-show="search || method || online || verified || dir !== 'default'" x-cloak
                 class="flex flex-wrap items-center gap-2 border-t border-neutral-100 pt-3 text-xs">
                <span class="font-medium text-neutral-400">{{ __('Filters:') }}</span>
                <span x-show="online" class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 font-medium text-green-700">{{ __('Online') }}
                    <button type="button" @click="online=false"><x-heroicon-o-x-mark class="h-3 w-3" /></button></span>
                <span x-show="verified" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 font-medium text-brand-700">{{ __('Verified') }}
                    <button type="button" @click="verified=false"><x-heroicon-o-x-mark class="h-3 w-3" /></button></span>
                <span x-show="method" class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-0.5 font-medium text-neutral-700" x-text="method"></span>
                <button type="button" @click="search=''; method=''; online=false; verified=false; dir='default'"
                        class="ml-auto font-medium text-neutral-500 hover:text-neutral-900">{{ __('Clear all') }}</button>
            </div>
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
                <div data-adrow
                     data-name="{{ strtolower($ad->user->name) }}"
                     data-online="{{ $online ? '1' : '0' }}"
                     data-verified="{{ $verified ? '1' : '0' }}"
                     data-methods="{{ $methodsCsv }}"
                     data-price="{{ $ad->fixed_price ?? 0 }}"
                     x-show="visible($el)" x-transition.opacity
                     :style="`order:${order($el)}`"
                     class="flex flex-col rounded-2xl border border-neutral-200 bg-white p-5 shadow-[var(--shadow-card)] transition-all hover:border-neutral-300 hover:shadow-md">

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
                            <span class="inline-flex items-center rounded-md border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-600">{{ $m->name }}</span>
                        @endforeach
                    </div>

                    {{-- Trade --}}
                    <button type="button"
                        class="mt-auto block w-full rounded-lg px-5 py-2.5 text-center text-sm font-semibold text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 {{ $buyActive ? 'bg-green-600 hover:bg-green-700 focus-visible:ring-green-400' : 'bg-red-600 hover:bg-red-500 focus-visible:ring-red-400' }}"
                        x-on:click="choose({ id: '{{ $ad->id }}', price: '{{ $ad->fixed_price ?? 0 }}', min: '{{ $ad->min_order }}', max: '{{ $ad->max_order }}', sym: '{{ $ad->asset->symbol }}', fiat: '{{ $ad->fiat_currency }}', who: '{{ addslashes($ad->user->name) }}', side: '{{ $want }}' })">
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

            {{-- Client-side "no matches" state --}}
            @if (count($ads))
                <div x-show="shown === 0" x-cloak class="sm:col-span-2 lg:col-span-3">
                    <div class="pp-row p-4">
                        <x-ui.empty-state icon="magnifying-glass" :title="__('No matching ads')"
                            :description="__('No advertisers match your filters. Try clearing a filter or widening your search.')" />
                    </div>
                </div>
            @endif
        </div>

        <div>{{ $ads->withQueryString()->links() }}</div>

        {{-- Order modal (shared, populated by Alpine) --}}
        <x-ui.modal name="p2p-order" :title="__('Place order')" maxWidth="sm">
            <form method="POST" action="{{ route('p2p.orders.store') }}" class="space-y-5" x-data="{ amount: '' }">
                @csrf
                <input type="hidden" name="ad_id" :value="ad?.id">

                <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3">
                    <span class="grid h-9 w-9 place-items-center rounded-full text-sm font-semibold text-white"
                          :class="ad?.side === 'buy' ? 'bg-green-600' : 'bg-red-600'"
                          x-text="(ad?.who || '?').slice(0,1).toUpperCase()"></span>
                    <div class="min-w-0 text-sm">
                        <p class="truncate font-semibold text-neutral-900" x-text="ad?.who"></p>
                        <p class="text-neutral-500">
                            <span class="tabular font-medium text-neutral-900" x-text="Number(ad?.price).toLocaleString()"></span>
                            <span x-text="ad?.fiat"></span> / <span x-text="ad?.sym"></span>
                        </p>
                    </div>
                    <span class="ml-auto rounded-full px-2.5 py-0.5 text-xs font-semibold"
                          :class="ad?.side === 'buy' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                          x-text="ad?.side === 'buy' ? 'BUY' : 'SELL'"></span>
                </div>

                <div>
                    <label class="pp-label">{{ __('Amount') }} (<span x-text="ad?.sym"></span>)</label>
                    <div class="relative">
                        <input type="text" name="amount" inputmode="decimal" x-model="amount" placeholder="0.00" class="pp-input pr-16" required>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-neutral-400" x-text="ad?.sym"></span>
                    </div>
                    <p class="mt-2 flex items-center justify-between text-xs text-neutral-500">
                        <span x-show="amount && ad">≈ <span class="font-semibold tabular text-neutral-700" x-text="(Number(amount) * Number(ad?.price)).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span> <span x-text="ad?.fiat"></span></span>
                        <span class="text-neutral-400">{{ __('Limit') }} <span class="tabular" x-text="Number(ad?.min).toLocaleString()"></span>–<span class="tabular" x-text="Number(ad?.max).toLocaleString()"></span></span>
                    </p>
                </div>

                <x-ui.button type="submit" class="w-full" :variant="$buyActive ? 'success' : 'danger'">
                    {{ $buyActive ? __('Buy') : __('Sell') }} &amp; {{ __('lock escrow') }}
                </x-ui.button>
                <p class="flex items-center justify-center gap-1.5 text-center text-xs text-neutral-400">
                    <x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> {{ __('USDT is escrowed instantly. Pay off-platform, then confirm.') }}
                </p>
            </form>
        </x-ui.modal>
    </div>
</x-layouts.app>
