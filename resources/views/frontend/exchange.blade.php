<x-layouts.app :title="__('Exchange')">
    @php
        // Coins selectable as "from" (have a fundable representative asset).
        $fromCoins = $coins->filter(fn ($c) => in_array($c['assetId'], $fromAssetIds, true))->values();
        $defaultFrom = (int) old('fromAssetId', $fromCoins->first()['assetId'] ?? ($coins->first()['assetId'] ?? 0));
        $toDefault = $coins->first(fn ($c) => $c['assetId'] !== $defaultFrom);
        $defaultTo = (int) old('toAssetId', $toDefault['assetId'] ?? 0);

        // The flashed quote only applies if it still matches the current form inputs.
        $activeQuote = null;
        if ($quote
            && (int) $quote['fromAssetId'] === $defaultFrom
            && (int) $quote['toAssetId'] === $defaultTo
            && (string) old('fromAmount') !== ''
            && ! $quote['expired']) {
            $activeQuote = $quote;
        }
    @endphp

    <div class="space-y-6">
        <div class="mx-auto max-w-lg text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Exchange') }}</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ __('Swap between coins at a live reference rate.') }}</p>
            @if ($recentCount > 0)
                <a href="{{ route('exchange.history') }}" class="group mt-3 inline-flex items-center gap-1 text-sm font-medium text-neutral-500 transition hover:text-brand-600">
                    {{ __('View swap history') }}
                    <x-heroicon-o-chevron-right class="h-4 w-4 transition group-hover:translate-x-0.5" />
                </a>
            @endif
        </div>

        <div class="mx-auto max-w-lg">
            @if ($coins->count() < 2)
                <x-ui.card>
                    <x-ui.empty-state icon="arrows-right-left" :title="__('Exchange unavailable')"
                        :description="__('No coins are available to swap right now.')" />
                </x-ui.card>
            @else
                <x-ui.card>
                    <form method="POST" action="{{ route('exchange.quote') }}" class="space-y-4"
                        x-data="{
                            coins: @js($coins),
                            balances: @js($balances),
                            fromIds: @js($fromAssetIds),
                            fromAssetId: {{ $defaultFrom }},
                            toAssetId: {{ $defaultTo }},
                            fromAmount: @js(old('fromAmount', '')),

                            get fromCoins() { return this.coins.filter(c => this.fromIds.includes(c.assetId)); },
                            get toCoins() { return this.coins.filter(c => c.assetId !== this.fromAssetId); },
                            get fromCoin() { return this.coins.find(c => c.assetId === this.fromAssetId) ?? null; },
                            get toCoin() { return this.coins.find(c => c.assetId === this.toAssetId) ?? null; },
                            get fromBalance() { return this.balances[this.fromAssetId] ?? null; },
                            get insufficient() {
                                if (! this.fromBalance || this.fromAmount.trim() === '') return false;
                                const amt = parseFloat(this.fromAmount), bal = parseFloat(this.fromBalance.available);
                                return ! isNaN(amt) && ! isNaN(bal) && amt > bal;
                            },
                            setMax() { if (this.fromBalance) this.fromAmount = this.fromBalance.available; },
                            get sameAsset() { return this.fromAssetId === this.toAssetId; },
                            get canGetQuote() { return ! this.sameAsset && ! this.insufficient; },
                            swap() {
                                if (! this.fromIds.includes(this.toAssetId)) return;
                                [this.fromAssetId, this.toAssetId] = [this.toAssetId, this.fromAssetId];
                            },
                        }">
                        @csrf
                        <input type="hidden" name="fromAssetId" :value="fromAssetId" />
                        <input type="hidden" name="toAssetId" :value="toAssetId" />

                        {{-- From --}}
                        <div class="rounded-2xl border p-4 transition" :class="insufficient ? 'border-rose-300 bg-rose-50/40' : 'border-neutral-200 bg-neutral-50/60'">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-medium text-neutral-500">{{ __('You pay') }}</span>
                                <button type="button" x-show="fromBalance" x-on:click="setMax()" class="text-xs font-medium text-neutral-500 transition hover:text-brand-700">
                                    {{ __('Balance:') }} <span class="tabular font-semibold text-neutral-700" x-text="fromBalance?.formatted"></span>
                                    <span class="ms-1 rounded bg-brand-50 px-1.5 py-0.5 font-semibold text-brand-700">{{ __('MAX') }}</span>
                                </button>
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="text" inputmode="decimal" name="fromAmount" x-model="fromAmount" placeholder="0.00" autocomplete="off"
                                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-3xl font-semibold text-neutral-900 placeholder:text-neutral-300 focus:ring-0" />

                                {{-- Coin pill (styled token selector; native select overlaid) --}}
                                <div class="relative flex shrink-0 items-center gap-2 rounded-full bg-white py-1.5 pl-1.5 pr-8 shadow-sm ring-1 ring-neutral-200 transition hover:ring-neutral-300">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-500 text-[10px] font-bold text-white" x-text="(fromCoin?.symbol ?? '').slice(0, 2)"></span>
                                    <span class="text-sm font-semibold text-neutral-900" x-text="fromCoin?.symbol"></span>
                                    <x-heroicon-o-chevron-down class="pointer-events-none absolute right-2.5 h-4 w-4 text-neutral-400" />
                                    <select x-model.number="fromAssetId" class="absolute inset-0 cursor-pointer opacity-0" aria-label="{{ __('Pay with') }}">
                                        <template x-for="c in fromCoins" :key="c.assetId">
                                            <option :value="c.assetId" x-text="c.symbol"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                            @error('fromAmount')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
                            <p x-show="insufficient" x-cloak class="mt-1.5 flex items-center gap-1 text-xs font-medium text-rose-600">
                                <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5" /> {{ __('More than your available balance.') }}
                            </p>
                        </div>

                        {{-- Swap direction --}}
                        <div class="-my-3 flex justify-center">
                            <button type="button" x-on:click="swap()" title="{{ __('Switch coins') }}"
                                class="grid h-11 w-11 place-items-center rounded-full border-4 border-white bg-neutral-100 text-neutral-500 shadow-sm transition hover:rotate-180 hover:bg-brand-500 hover:text-white">
                                <x-heroicon-o-arrows-up-down class="h-4 w-4" />
                            </button>
                        </div>

                        {{-- To --}}
                        <div class="rounded-2xl border border-neutral-200 bg-neutral-50/60 p-4">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-medium text-neutral-500">{{ __('You receive') }} <span class="text-neutral-400">({{ __('estimated') }})</span></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="tabular min-w-0 flex-1 truncate text-3xl font-semibold {{ ($activeQuote['toAmount'] ?? null) ? 'text-neutral-900' : 'text-neutral-300' }}">{{ $activeQuote['toAmount'] ?? '0.00' }}</span>

                                <div class="relative flex shrink-0 items-center gap-2 rounded-full bg-white py-1.5 pl-1.5 pr-8 shadow-sm ring-1 ring-neutral-200 transition hover:ring-neutral-300">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-neutral-800 text-[10px] font-bold text-white" x-text="(toCoin?.symbol ?? '').slice(0, 2)"></span>
                                    <span class="text-sm font-semibold text-neutral-900" x-text="toCoin?.symbol"></span>
                                    <x-heroicon-o-chevron-down class="pointer-events-none absolute right-2.5 h-4 w-4 text-neutral-400" />
                                    <select x-model.number="toAssetId" class="absolute inset-0 cursor-pointer opacity-0" aria-label="{{ __('Receive') }}">
                                        <template x-for="c in toCoins" :key="c.assetId">
                                            <option :value="c.assetId" x-text="c.symbol"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                            <p x-show="sameAsset" x-cloak class="mt-1.5 text-xs font-medium text-rose-600">{{ __('Choose two different coins.') }}</p>
                            @error('toAssetId')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
                            @error('fromAssetId')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        @unless ($activeQuote)
                            <x-ui.button type="submit" size="lg" class="w-full" icon="calculator" x-bind:disabled="! canGetQuote">
                                <span x-show="! insufficient">{{ __('Get quote') }}</span>
                                <span x-show="insufficient" x-cloak>{{ __('Insufficient balance') }}</span>
                            </x-ui.button>
                        @endunless
                    </form>

                    {{-- Quote details + countdown + confirm (shown when a matching quote is flashed) --}}
                    @if ($activeQuote)
                        <div class="mt-4 space-y-4" x-data="{
                                expiresAt: {{ $activeQuote['expiresAt'] }},
                                now: Math.floor(Date.now() / 1000),
                                get remaining() { return Math.max(0, this.expiresAt - this.now); },
                                init() {
                                    const t = setInterval(() => {
                                        this.now = Math.floor(Date.now() / 1000);
                                        if (this.remaining <= 0) clearInterval(t);
                                    }, 1000);
                                },
                            }">
                            <div class="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm">
                                <div class="flex items-center justify-between text-neutral-600">
                                    <span>{{ __('Rate') }}</span>
                                    <span class="tabular font-medium text-neutral-900">1 {{ $activeQuote['fromSymbol'] }} ≈ {{ $activeQuote['rate'] }} {{ $activeQuote['toSymbol'] }}</span>
                                </div>
                                <div class="flex items-center justify-between text-neutral-600">
                                    <span>{{ __('Spread') }}</span>
                                    <span class="tabular font-medium text-neutral-900">{{ $activeQuote['spread'] }}%</span>
                                </div>
                                <div class="flex items-center justify-between border-t border-neutral-200 pt-2 text-neutral-600">
                                    <span>{{ __('Quote expires in') }}</span>
                                    <span class="tabular inline-flex items-center gap-1 font-semibold" :class="remaining > 0 ? 'text-amber-700' : 'text-rose-600'">
                                        <x-heroicon-o-clock class="h-3.5 w-3.5" />
                                        <span x-show="remaining > 0"><span x-text="remaining"></span>s</span>
                                        <span x-show="remaining <= 0" x-cloak>{{ __('Expired') }}</span>
                                    </span>
                                </div>
                            </div>

                            @error('quoteId')<x-ui.alert type="danger">{{ $message }}</x-ui.alert>@enderror

                            <div class="space-y-2">
                                <form method="POST" action="{{ route('exchange.confirm') }}">
                                    @csrf
                                    <input type="hidden" name="quoteId" value="{{ $activeQuote['quoteId'] }}" />
                                    <x-ui.button type="submit" variant="success" class="w-full" icon="check"
                                        x-bind:disabled="remaining <= 0">{{ __('Confirm swap') }}</x-ui.button>
                                </form>

                                {{-- Refresh quote: re-post the same inputs --}}
                                <form method="POST" action="{{ route('exchange.quote') }}">
                                    @csrf
                                    <input type="hidden" name="fromAssetId" value="{{ $activeQuote['fromAssetId'] }}" />
                                    <input type="hidden" name="toAssetId" value="{{ $activeQuote['toAssetId'] }}" />
                                    <input type="hidden" name="fromAmount" value="{{ $activeQuote['fromAmountInput'] }}" />
                                    <button type="submit" class="w-full text-center text-xs font-medium text-neutral-500 hover:text-amber-700">{{ __('Refresh quote') }}</button>
                                </form>
                            </div>
                        </div>
                    @endif
                </x-ui.card>

            @endif
        </div>
    </div>
</x-layouts.app>
