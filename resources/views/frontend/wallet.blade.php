<x-layouts.app :title="'Wallet'">
    @php
        $iconBg = fn (string $symbol) => [
            'USDT' => 'bg-emerald-500', 'USDC' => 'bg-sky-500', 'ETH' => 'bg-indigo-500',
            'BNB' => 'bg-amber-500', 'TRX' => 'bg-rose-500', 'BTC' => 'bg-orange-500',
            'BDT' => 'bg-green-600', 'USD' => 'bg-neutral-600', 'EUR' => 'bg-blue-600',
        ][$symbol] ?? 'bg-brand-500';

        // Server-side filtering + search (mirrors the old Alpine visibleWallets).
        $q = mb_strtolower($search);
        $visibleWallets = collect($wallets)->filter(function ($w) use ($filter, $q) {
            if ($filter === 'crypto' && $w['isFiat']) return false;
            if ($filter === 'fiat' && ! $w['isFiat']) return false;
            if ($q !== '' && ! str_contains(mb_strtolower($w['symbol']), $q) && ! str_contains(mb_strtolower($w['name']), $q)) return false;
            return true;
        })->values();
    @endphp

    <div class="space-y-6">
        <x-ui.page-header title="Wallet" subtitle="All your balances across crypto and Taka in one place." />

        {{-- Portfolio hero with inline quick actions --}}
        <div x-data="{ obscured: $persist(false).as('pp_hide_balance') }"
            class="pp-card relative overflow-hidden border-brand-100 bg-gradient-to-br from-white to-brand-50 p-6 sm:p-7">
            <div class="absolute -right-8 -top-10 h-40 w-40 rounded-full bg-brand-300/20 blur-3xl"></div>
            <div class="absolute -bottom-12 -left-6 h-32 w-32 rounded-full bg-brand-200/25 blur-2xl"></div>

            <div class="relative">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-brand-700">Total balance</p>
                        <div class="mt-1.5">
                            <p class="tabular text-4xl font-bold tracking-tight text-neutral-900" x-show="!obscured">{{ $totalValue }}</p>
                            <p class="text-4xl font-bold tracking-tight text-neutral-900" x-show="obscured" x-cloak>••••••</p>
                        </div>
                        <p class="mt-2 text-xs text-neutral-500">{{ $fundedCount }} of {{ $totalAssets }} assets funded · estimated value</p>
                    </div>
                    <button type="button" x-on:click="obscured = !obscured" class="rounded-lg p-1.5 text-neutral-400 transition hover:bg-white/70 hover:text-neutral-700" title="Hide balance">
                        <x-heroicon-o-eye x-show="!obscured" class="h-5 w-5" />
                        <x-heroicon-o-eye-slash x-show="obscured" x-cloak class="h-5 w-5" />
                    </button>
                </div>

                {{-- Quick actions --}}
                <div class="mt-6 grid grid-cols-4 gap-2 sm:mt-7 sm:max-w-md">
                    @php
                        $actions = [
                            ['route' => route('deposit'), 'label' => 'Deposit', 'icon' => 'arrow-down-tray'],
                            ['route' => route('withdraw'), 'label' => 'Withdraw', 'icon' => 'arrow-up-tray'],
                            ['route' => route('send'), 'label' => 'Send', 'icon' => 'paper-airplane'],
                            ['route' => route('exchange'), 'label' => 'Swap', 'icon' => 'arrows-right-left'],
                        ];
                    @endphp
                    @foreach ($actions as $a)
                        <a href="{{ $a['route'] }}" class="group flex flex-col items-center gap-1.5">
                            <span class="grid h-11 w-11 place-items-center rounded-full bg-white text-brand-600 shadow-sm ring-1 ring-brand-100 transition group-hover:bg-brand-500 group-hover:text-white group-hover:ring-brand-500">
                                <x-dynamic-component :component="'heroicon-o-'.$a['icon']" class="h-5 w-5" />
                            </span>
                            <span class="text-[11px] font-medium text-neutral-600 group-hover:text-neutral-900">{{ $a['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- 30-day flow analytics --}}
        <div class="grid gap-4 sm:grid-cols-4">
            @php
                $tiles = [
                    ['label' => 'Funded assets', 'value' => $fundedCount.' / '.$totalAssets, 'icon' => 'squares-2x2', 'bg' => 'bg-brand-100', 'fg' => 'text-brand-600'],
                    ['label' => 'Inflow · 30d', 'value' => $analytics['inflow'], 'icon' => 'arrow-down-left', 'bg' => 'bg-emerald-100', 'fg' => 'text-emerald-500'],
                    ['label' => 'Outflow · 30d', 'value' => $analytics['outflow'], 'icon' => 'arrow-up-right', 'bg' => 'bg-rose-100', 'fg' => 'text-rose-500'],
                    ['label' => 'Net · 30d', 'value' => $analytics['net'], 'icon' => 'chart-bar', 'bg' => $analytics['netPositive'] ? 'bg-emerald-100' : 'bg-amber-100', 'fg' => $analytics['netPositive'] ? 'text-emerald-500' : 'text-amber-500'],
                ];
            @endphp
            @foreach ($tiles as $t)
                <div class="pp-card flex items-center gap-4 p-5">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl {{ $t['bg'] }} {{ $t['fg'] }}">
                        <x-dynamic-component :component="'heroicon-o-'.$t['icon']" class="h-6 w-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $t['label'] }}</p>
                        <p class="tabular mt-1 text-2xl font-bold tracking-tight text-neutral-800">{{ $t['value'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Holdings: single-line toolbar (filter + search) --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex rounded-xl bg-neutral-100 p-1">
                @foreach (['all' => 'All', 'crypto' => 'Crypto', 'fiat' => 'Fiat'] as $key => $label)
                    <a href="{{ route('wallet', array_merge(request()->query(), ['filter' => $key])) }}"
                        class="rounded-lg px-4 py-1.5 text-sm font-medium transition {{ $filter === $key ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-500 hover:text-neutral-800' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('wallet') }}" class="relative sm:w-64">
                <input type="hidden" name="filter" value="{{ $filter }}" />
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                <input type="search" name="search" value="{{ $search }}" placeholder="Search assets…" class="pp-input w-full !pl-10 text-sm" />
            </form>
        </div>

        {{-- Holdings table --}}
        @if ($visibleWallets->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[var(--shadow-card)]">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/60 text-[11px] uppercase tracking-wider text-gray-400">
                            <th class="px-5 py-3 text-left font-semibold">Asset</th>
                            <th class="px-5 py-3 text-right font-semibold">Available</th>
                            <th class="px-5 py-3 text-right font-semibold">Est. value</th>
                            <th class="w-8 px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($visibleWallets as $w)
                            <tr class="group cursor-pointer transition hover:bg-gray-50/70" onclick="window.location='{{ route('wallet.show', $w['symbol']) }}'">
                                <td class="px-5 py-4 align-middle">
                                    <div class="flex items-center gap-3">
                                        <form method="POST" action="{{ route('wallet.favorite', $w['assetId']) }}" onclick="event.stopPropagation()">
                                            @csrf
                                            <button type="submit" class="block text-neutral-300 transition hover:scale-110 hover:text-brand-500" title="Toggle favorite">
                                                @if ($w['favorite'])
                                                    <svg class="h-4 w-4 text-brand-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" /></svg>
                                                @else
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg>
                                                @endif
                                            </button>
                                        </form>
                                        <span class="inline-grid h-10 w-10 shrink-0 place-items-center rounded-full text-xs font-bold text-white {{ $iconBg($w['symbol']) }}">{{ \Illuminate\Support\Str::substr($w['symbol'], 0, 4) }}</span>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <p class="text-sm font-semibold text-neutral-900">{{ $w['symbol'] }}</p>
                                                <span @class([
                                                    'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                    'bg-emerald-50 text-emerald-700' => $w['isFiat'],
                                                    'bg-sky-50 text-sky-700' => ! $w['isFiat'],
                                                ])>{{ $w['isFiat'] ? 'Fiat' : 'Crypto' }}</span>
                                            </div>
                                            <p class="truncate text-xs text-neutral-500">
                                                {{ $w['name'] }}@if (($w['networks'] ?? 1) > 1) <span class="text-neutral-400">· {{ $w['networks'] }} networks</span>@endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                                    <p class="tabular text-sm font-semibold text-neutral-900">{{ $w['available'] }}</p>
                                    @if ($w['locked'])
                                        <p class="tabular mt-0.5 text-[11px] text-amber-600">{{ $w['locked'] }} locked</p>
                                    @endif
                                </td>
                                <td class="tabular whitespace-nowrap px-5 py-4 text-right align-middle text-sm text-neutral-500">{{ $w['fiatValue'] ?? '—' }}</td>
                                <td class="px-5 py-4 text-right align-middle">
                                    <x-heroicon-o-chevron-right class="ml-auto h-4 w-4 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-neutral-500" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="magnifying-glass" title="No assets found"
                    description="No wallets match your search or filter. Try a different term." />
            </div>
        @endif
    </div>
</x-layouts.app>
