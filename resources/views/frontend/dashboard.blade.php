<x-layouts.app :title="'Dashboard'">
    @php
        $hour = (int) now()->format('G');
        $greeting = 'Good '.($hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening'));

        $activityWrap = fn (?string $color) => [
            'success' => 'bg-emerald-50 text-emerald-600',
            'warning' => 'bg-amber-50 text-amber-600',
            'info' => 'bg-sky-50 text-sky-600',
        ][$color] ?? 'bg-neutral-100 text-neutral-500';

        $activityPath = fn (?string $icon) => [
            'arrow-down-left' => 'M7 7l10 10M17 7v10H7',
            'arrow-up-right' => 'M17 17L7 7M7 17V7h10',
            'paper-airplane' => 'M6 12L3.27 3.27a.5.5 0 0 1 .68-.62l16.5 8.25a.5.5 0 0 1 0 .9L4.05 20.35a.5.5 0 0 1-.68-.62L6 12Zm0 0h7',
        ][$icon] ?? '';

        $pending = $pendingDeposits + $pendingWithdrawals;
    @endphp

    <div class="space-y-6">
        {{-- Greeting --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-base font-semibold tracking-tight text-neutral-900 sm:text-lg">
                {{ $greeting }}, {{ $firstName }}
            </h1>
            <p class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
                <x-heroicon-o-calendar-days class="h-4 w-4 text-neutral-400" />
                {{ now()->format('l, j M Y') }}
            </p>
        </div>

        {{-- KYC nudge --}}
        @if ($needsKyc)
            <div class="flex flex-col items-start justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 sm:flex-row sm:items-center">
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-500 text-white">
                        <x-heroicon-o-identification class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-neutral-900">Finish verifying your account</p>
                        <p class="text-xs text-neutral-600">Unlock higher withdrawal limits, cards and more.</p>
                    </div>
                </div>
                <x-ui.button href="{{ route('settings', ['tab' => 'verification']) }}" size="sm" icon="arrow-right">Verify now</x-ui.button>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Balance hero --}}
            <div class="pp-card p-6 lg:col-span-2" x-data="{ hidden: $persist(false).as('pp_hide_balance') }">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-neutral-500">Total balance · {{ $baseCurrency }}</p>
                    <button type="button" x-on:click="hidden = !hidden" class="rounded-full p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600" aria-label="Toggle balance visibility">
                        <x-heroicon-o-eye x-show="!hidden" class="h-5 w-5" />
                        <x-heroicon-o-eye-slash x-show="hidden" class="h-5 w-5" x-cloak />
                    </button>
                </div>
                <p class="tabular mt-2 text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">
                    <span x-show="!hidden">{{ $portfolioValue }}</span>
                    <span x-show="hidden" x-cloak>••••••</span>
                </p>
                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-500">
                    <span>{{ $assetCount }} {{ $assetCount === 1 ? 'asset' : 'assets' }}</span>
                    @if ($hasLocked)
                        <span class="inline-flex items-center gap-1">
                            <x-heroicon-o-lock-closed class="h-3.5 w-3.5" />
                            <span x-show="!hidden">{{ $lockedValue }}</span><span x-show="hidden" x-cloak>••••</span> locked
                        </span>
                    @endif
                    @if ($pending > 0)
                        <span class="inline-flex items-center gap-1">
                            <x-heroicon-o-clock class="h-3.5 w-3.5" />
                            {{ $pending }} pending
                        </span>
                    @endif
                </div>

                {{-- Quick actions --}}
                <div class="mt-6 grid grid-cols-4 gap-2">
                    <a href="{{ route('deposit') }}" class="group flex flex-col items-center gap-1.5 rounded-xl bg-neutral-100 py-3 text-xs font-semibold text-neutral-800 transition hover:bg-brand-50 hover:text-neutral-900">
                        <x-heroicon-o-arrow-down-tray class="h-6 w-6 text-brand-600 transition group-hover:text-brand-700" />
                        Deposit
                    </a>
                    <a href="{{ route('send') }}" class="group flex flex-col items-center gap-1.5 rounded-xl bg-neutral-100 py-3 text-xs font-semibold text-neutral-800 transition hover:bg-brand-50 hover:text-neutral-900">
                        <x-heroicon-o-paper-airplane class="h-6 w-6 text-brand-600 transition group-hover:text-brand-700" />
                        Send
                    </a>
                    <a href="{{ route('exchange') }}" class="group flex flex-col items-center gap-1.5 rounded-xl bg-neutral-100 py-3 text-xs font-semibold text-neutral-800 transition hover:bg-brand-50 hover:text-neutral-900">
                        <x-heroicon-o-arrows-right-left class="h-6 w-6 text-brand-600 transition group-hover:text-brand-700" />
                        Swap
                    </a>
                    <a href="{{ route('wallet') }}" class="group flex flex-col items-center gap-1.5 rounded-xl bg-neutral-100 py-3 text-xs font-semibold text-neutral-800 transition hover:bg-brand-50 hover:text-neutral-900">
                        <x-heroicon-o-wallet class="h-6 w-6 text-brand-600 transition group-hover:text-brand-700" />
                        Wallet
                    </a>
                </div>
            </div>

            {{-- Allocation --}}
            <x-ui.card title="Allocation">
                @if ($assetCount > 0)
                    <div class="flex items-center gap-4">
                        <div class="relative h-32 w-32 shrink-0" x-data="chart(@js($allocationConfig))">
                            <canvas x-ref="canvas"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-lg font-bold text-neutral-900">{{ $assetCount }}</span>
                                <span class="text-[10px] uppercase tracking-wide text-neutral-400">assets</span>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1 space-y-1.5">
                            @foreach (array_slice($funded, 0, 5) as $row)
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span>
                                    <span class="font-medium text-neutral-700">{{ $row['symbol'] }}</span>
                                    <span class="tabular ml-auto text-neutral-500">{{ $row['share'] }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <x-ui.empty-state icon="chart-pie" title="No holdings" description="Fund your wallet to see allocation." />
                @endif
            </x-ui.card>
        </div>

        {{-- 30-day money flow --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card label="Inflow · 30d" :value="$analytics['inflow']" icon="arrow-down-left" accent="emerald" />
            <x-ui.stat-card label="Outflow · 30d" :value="$analytics['outflow']" icon="arrow-up-right" accent="rose" />
            <x-ui.stat-card label="Net · 30d" :value="$analytics['net']" icon="chart-bar" :accent="$analytics['netPositive'] ? 'emerald' : 'amber'" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Assets --}}
            <div class="lg:col-span-2">
                <x-ui.card title="Your assets">
                    <x-slot:actions>
                        <x-ui.button href="{{ route('wallet') }}" variant="ghost" size="sm" iconRight="arrow-right">All</x-ui.button>
                    </x-slot:actions>

                    @if (count($funded))
                        <div>
                            @foreach ($funded as $i => $row)
                                <a href="{{ route('wallet.show', $row['symbol']) }}"
                                    @class(['-mx-2 flex items-center gap-4 rounded-xl px-2 py-2.5 transition hover:bg-neutral-50', 'border-b border-neutral-100' => $i < count($funded) - 1])>
                                    <span class="inline-grid h-11 w-11 shrink-0 place-items-center rounded-full text-sm font-bold text-white"
                                        style="background: {{ $row['color'] }}">{{ \Illuminate\Support\Str::substr($row['symbol'], 0, 4) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="truncate text-sm font-semibold text-neutral-900">{{ $row['symbol'] }}</p>
                                            <p class="tabular text-sm font-semibold text-neutral-900">{{ $row['available'] }}</p>
                                        </div>
                                        <div class="mt-1 flex items-center justify-between gap-2">
                                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-neutral-100">
                                                <div class="h-full rounded-full" style="width: {{ min(100, $row['share']) }}%; background: {{ $row['color'] }}"></div>
                                            </div>
                                            <p class="tabular text-xs text-neutral-500">{{ $row['fiat'] }}</p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <x-ui.empty-state icon="wallet" title="No funds yet"
                            description="Deposit crypto or top up your Taka balance to get started.">
                            <x-slot:action>
                                <x-ui.button href="{{ route('deposit') }}" icon="arrow-down-tray">Make a deposit</x-ui.button>
                            </x-slot:action>
                        </x-ui.empty-state>
                    @endif
                </x-ui.card>
            </div>

            {{-- Recent activity --}}
            <div>
                <x-ui.card title="Recent activity">
                    @if (count($recent))
                        <div>
                            @foreach ($recent as $i => $item)
                                <div @class(['flex items-center gap-3 py-2.5', 'border-b border-neutral-100' => $i < count($recent) - 1])>
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full {{ $activityWrap($item['color']) }}">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $activityPath($item['icon']) }}" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-neutral-900">{{ $item['title'] }}</p>
                                        <p class="text-xs text-neutral-500">{{ $item['at_human'] }} · {{ $item['status'] }}</p>
                                    </div>
                                    <p class="tabular text-sm font-semibold {{ str_starts_with($item['amount'], '+') ? 'text-emerald-600' : 'text-neutral-700' }}">{{ $item['amount'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-ui.empty-state icon="clock" title="No activity yet" description="Your transactions will appear here." />
                    @endif
                </x-ui.card>
            </div>
        </div>
    </div>
</x-layouts.app>
