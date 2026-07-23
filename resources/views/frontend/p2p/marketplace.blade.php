<x-layouts.app :title="'P2P Marketplace'">
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
        <x-ui.page-header title="P2P Marketplace" subtitle="Buy and sell USDT peer-to-peer — every trade is protected by escrow until both sides confirm.">
            <x-slot:actions>
                <a href="{{ route('p2p.orders') }}"><x-ui.button variant="secondary" icon="clock">My orders</x-ui.button></a>
                <a href="{{ route('p2p.ads') }}"><x-ui.button variant="secondary" icon="megaphone">My ads</x-ui.button></a>
                <a href="{{ route('p2p.ads.create') }}"><x-ui.button icon="plus">Post ad</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Market snapshot --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-[var(--shadow-card)]">
                <p class="text-xs font-medium text-neutral-500">Best {{ $buyActive ? 'buy' : 'sell' }} price</p>
                <p class="mt-1 text-xl font-bold tabular text-neutral-900">
                    @if ($bestPrice){{ number_format($bestPrice, 2) }} <span class="text-sm font-medium text-neutral-400">{{ $fiat }}</span>@else — @endif
                </p>
            </div>
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-[var(--shadow-card)]">
                <p class="text-xs font-medium text-neutral-500">Live ads</p>
                <p class="mt-1 text-xl font-bold tabular text-neutral-900">{{ $ads->total() }}</p>
            </div>
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-[var(--shadow-card)]">
                <p class="text-xs font-medium text-neutral-500">Advertisers online</p>
                <p class="mt-1 flex items-center gap-2 text-xl font-bold tabular text-neutral-900">
                    <span class="h-2 w-2 rounded-full bg-green-500"></span>{{ $onlineCount }}
                </p>
            </div>
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-[var(--shadow-card)]">
                <p class="text-xs font-medium text-neutral-500">Asset</p>
                <p class="mt-1 flex items-center gap-2 text-xl font-bold text-neutral-900">
                    <x-ui.asset-icon symbol="USDT" size="sm" /> USDT
                </p>
            </div>
        </div>

        {{-- Buy / Sell + sticky filter toolbar --}}
        <div class="pp-toolbar space-y-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="pp-seg">
                    <a href="{{ route('p2p', ['side' => 'buy']) }}"
                       class="{{ $buyActive ? 'bg-green-600 text-white shadow-sm' : 'text-neutral-500 hover:text-neutral-900' }}">Buy USDT</a>
                    <a href="{{ route('p2p', ['side' => 'sell']) }}"
                       class="{{ ! $buyActive ? 'bg-red-600 text-white shadow-sm' : 'text-neutral-500 hover:text-neutral-900' }}">Sell USDT</a>
                </div>

                {{-- Price sort --}}
                <div class="flex items-center gap-2">
                    <span class="text-sm text-neutral-500">Sort</span>
                    <select x-model="dir" class="pp-input h-10 w-auto min-h-0 py-0 pl-3 pr-8 text-sm">
                        <option value="default">Recommended</option>
                        <option value="asc">Price: low → high</option>
                        <option value="desc">Price: high → low</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Search --}}
                <div class="relative min-w-[14rem] flex-1">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                    <input type="text" x-model="search" placeholder="Search advertiser…" class="pp-input h-10 min-h-0 py-0 pl-9 text-sm">
                </div>

                {{-- Payment method --}}
                <select x-model="method" class="pp-input h-10 w-auto min-h-0 py-0 pl-3 pr-8 text-sm">
                    <option value="">All payments</option>
                    @foreach ($methods as $m)
                        <option value="{{ strtolower($m->name) }}">{{ $m->name }}</option>
                    @endforeach
                </select>

                {{-- Toggles --}}
                <button type="button" class="pp-chip" :class="{ 'is-on': online }" @click="online = !online">
                    <span class="h-2 w-2 rounded-full bg-green-500"></span> Online only
                </button>
                <button type="button" class="pp-chip" :class="{ 'is-on': verified }" @click="verified = !verified">
                    <x-heroicon-s-check-badge class="h-4 w-4 text-brand-500" /> Verified only
                </button>

                <button type="button" x-show="search || method || online || verified || dir !== 'default'" x-cloak
                        @click="search=''; method=''; online=false; verified=false; dir='default'"
                        class="ml-auto text-sm font-medium text-neutral-500 hover:text-neutral-900">Reset</button>
            </div>
        </div>

        {{-- Column labels (desktop) --}}
        <div class="hidden px-5 lg:grid lg:grid-cols-[1.7fr_1fr_1.2fr_1.4fr_auto] lg:gap-4 lg:text-xs lg:font-semibold lg:uppercase lg:tracking-wider lg:text-neutral-400">
            <span>Advertiser</span>
            <span>Price</span>
            <span>Available / Limit</span>
            <span>Payment</span>
            <span class="text-right">Trade</span>
        </div>

        {{-- Ad list --}}
        <div class="flex flex-col gap-3">
            @forelse ($ads as $ad)
                @php
                    $p = $profiles[$ad->user_id] ?? null;
                    $online = $p->is_online ?? false;
                    $level = $p->level ?? 0;
                    $verified = $level >= 2;
                    $methodsCsv = strtolower($ad->paymentMethods->pluck('name')->implode(','));
                    $release = $p?->avg_release_seconds;
                @endphp
                <div data-adrow
                     data-name="{{ strtolower($ad->user->name) }}"
                     data-online="{{ $online ? '1' : '0' }}"
                     data-verified="{{ $verified ? '1' : '0' }}"
                     data-methods="{{ $methodsCsv }}"
                     data-price="{{ $ad->fixed_price ?? 0 }}"
                     x-show="visible($el)" x-transition.opacity
                     :style="`order:${order($el)}`"
                     class="pp-row grid grid-cols-1 gap-4 p-4 sm:p-5 lg:grid-cols-[1.7fr_1fr_1.2fr_1.4fr_auto] lg:items-center">

                    {{-- Advertiser --}}
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <x-ui.avatar :name="$ad->user->name" size="md" />
                            @if ($online)
                                <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-green-500" title="Online"></span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <a href="{{ route('p2p.merchant', $ad->user_id) }}" class="flex items-center gap-1.5 truncate text-sm font-semibold text-neutral-900 hover:text-brand-600">
                                {{ $ad->user->name }}
                                @if ($verified)<x-heroicon-s-check-badge class="h-4 w-4 shrink-0 text-brand-500" title="Verified merchant" />@endif
                            </a>
                            <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-neutral-500">
                                <span>{{ $p->trade_count ?? 0 }} trades</span>
                                <span class="text-neutral-300">·</span>
                                <span>{{ number_format(($p->completion_rate_bps ?? 0) / 100, 1) }}% completion</span>
                                @if ($release)
                                    <span class="text-neutral-300">·</span>
                                    <span class="inline-flex items-center gap-1"><x-heroicon-o-bolt class="h-3 w-3" />~{{ max(1, (int) round($release / 60)) }}m</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Price --}}
                    <div>
                        <p class="text-[0.7rem] font-medium uppercase tracking-wide text-neutral-400 lg:hidden">Price</p>
                        @if ($ad->price_type->value === 'fixed')
                            <p class="text-lg font-bold tabular text-neutral-900">{{ number_format((float) $ad->fixed_price, 2) }}
                                <span class="text-xs font-medium text-neutral-400">{{ $ad->fiat_currency }}</span></p>
                        @else
                            <p class="text-sm font-bold text-neutral-900">Floating</p>
                            <p class="text-xs text-neutral-400">market {{ $ad->margin_bps >= 0 ? '+' : '' }}{{ number_format($ad->margin_bps / 100, 2) }}%</p>
                        @endif
                    </div>

                    {{-- Available / Limit --}}
                    <div>
                        <p class="text-[0.7rem] font-medium uppercase tracking-wide text-neutral-400 lg:hidden">Available / Limit</p>
                        <p class="text-sm font-semibold tabular text-neutral-900">{{ $ad->availableMoney()->format() }}</p>
                        <p class="text-xs tabular text-neutral-400">{{ number_format((float) $ad->min_order, 0) }}–{{ number_format((float) $ad->max_order, 0) }} {{ $ad->fiat_currency }}</p>
                    </div>

                    {{-- Payment --}}
                    <div>
                        <p class="mb-1 text-[0.7rem] font-medium uppercase tracking-wide text-neutral-400 lg:hidden">Payment</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($ad->paymentMethods as $m)
                                <span class="inline-flex items-center rounded-md border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-600">{{ $m->name }}</span>
                            @endforeach
                        </div>
                    </div>

                    {{-- Trade --}}
                    <div class="lg:text-right">
                        <x-ui.button :variant="$buyActive ? 'success' : 'danger'" class="w-full lg:w-auto"
                            x-on:click="choose({ id: '{{ $ad->id }}', price: '{{ $ad->fixed_price ?? 0 }}', min: '{{ $ad->min_order }}', max: '{{ $ad->max_order }}', sym: '{{ $ad->asset->symbol }}', fiat: '{{ $ad->fiat_currency }}', who: '{{ addslashes($ad->user->name) }}', side: '{{ $want }}' })">
                            {{ $buyActive ? 'Buy' : 'Sell' }} USDT
                        </x-ui.button>
                    </div>
                </div>
            @empty
                <div class="pp-row p-4">
                    <x-ui.empty-state icon="user-group" title="No ads yet"
                        description="No {{ $want }} ads are live right now. Check back soon or post your own to get started.">
                        <x-slot:action>
                            <a href="{{ route('p2p.ads.create') }}"><x-ui.button icon="plus">Post an ad</x-ui.button></a>
                        </x-slot:action>
                    </x-ui.empty-state>
                </div>
            @endforelse

            {{-- Client-side "no matches" state --}}
            @if (count($ads))
                <div x-show="shown === 0" x-cloak class="pp-row p-4">
                    <x-ui.empty-state icon="magnifying-glass" title="No matching ads"
                        description="No advertisers match your filters. Try clearing a filter or widening your search." />
                </div>
            @endif
        </div>

        <div>{{ $ads->withQueryString()->links() }}</div>

        {{-- Order modal (shared, populated by Alpine) --}}
        <x-ui.modal name="p2p-order" title="Place order" maxWidth="sm">
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
                    <label class="pp-label">Amount (<span x-text="ad?.sym"></span>)</label>
                    <div class="relative">
                        <input type="text" name="amount" inputmode="decimal" x-model="amount" placeholder="0.00" class="pp-input pr-16" required>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-neutral-400" x-text="ad?.sym"></span>
                    </div>
                    <p class="mt-2 flex items-center justify-between text-xs text-neutral-500">
                        <span x-show="amount && ad">≈ <span class="font-semibold tabular text-neutral-700" x-text="(Number(amount) * Number(ad?.price)).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span> <span x-text="ad?.fiat"></span></span>
                        <span class="text-neutral-400">Limit <span class="tabular" x-text="Number(ad?.min).toLocaleString()"></span>–<span class="tabular" x-text="Number(ad?.max).toLocaleString()"></span></span>
                    </p>
                </div>

                <x-ui.button type="submit" class="w-full" :variant="$buyActive ? 'success' : 'danger'">
                    {{ $buyActive ? 'Buy' : 'Sell' }} &amp; lock escrow
                </x-ui.button>
                <p class="flex items-center justify-center gap-1.5 text-center text-xs text-neutral-400">
                    <x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> USDT is escrowed instantly. Pay off-platform, then confirm.
                </p>
            </form>
        </x-ui.modal>
    </div>
</x-layouts.app>
