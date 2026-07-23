<x-layouts.admin :title="__('Revenue Wallet')">
    <div class="space-y-6" x-data="{ showWithdraw: {{ $errors->any() ? 'true' : 'false' }} }">
        <x-ui.page-header :title="__('Revenue Wallet')" :subtitle="__('Company earnings from fees, card spend and FX spread — derived directly from the ledger.')">
            @if ($canWithdraw && $asset)
                <x-slot:actions>
                    <x-ui.button type="button" x-on:click="showWithdraw = true" icon="banknotes">{{ __('Withdraw') }}</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if (! $asset)
            <x-ui.empty-state icon="banknotes" :title="__('No asset configured')" :description="__('Add a crypto asset (e.g. USDT) to see revenue.')" />
        @else
            {{-- Balance card --}}
            <div class="pp-card overflow-hidden p-6">
                <div class="flex flex-wrap items-center justify-between gap-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ __('Available revenue') }} · {{ $asset->symbol }}</p>
                        <p class="tabular mt-2 text-4xl font-bold tracking-tight text-emerald-600">{{ $balance }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ __('Cumulative withdrawn:') }} <span class="tabular font-semibold text-neutral-700">{{ $withdrawn }}</span></p>
                    </div>
                    <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-emerald-100 text-emerald-500">
                        <x-heroicon-o-wallet class="h-8 w-8" />
                    </span>
                </div>
            </div>

            {{-- Stat tiles --}}
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.stat-card :label="__('Today')" :value="$stats['today']->format()" icon="sun" accent="emerald" />
                <x-ui.stat-card :label="__('This Week')" :value="$stats['week']->format()" icon="calendar-days" accent="brand" />
                <x-ui.stat-card :label="__('This Month')" :value="$stats['month']->format()" icon="calendar" accent="amber" />
                <x-ui.stat-card :label="__('Lifetime')" :value="$stats['lifetime']->format()" icon="banknotes" accent="emerald" />
            </div>

            {{-- Charts --}}
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div class="pp-card p-5">
                    <h2 class="flex items-center text-md font-semibold text-neutral-900">
                        <x-heroicon-o-arrow-trending-up class="mr-2 inline-block h-6 w-6 text-emerald-500" />
                        <span>{{ __('Daily revenue') }} <span class="font-normal text-neutral-400">{{ __('· last 14 days') }}</span></span>
                    </h2>
                    <div x-data="{
                            init() {
                                window.ppChart(this.$refs.canvas, {
                                    type: 'line',
                                    data: {
                                        labels: @js($dailyLabels),
                                        datasets: [{
                                            label: 'Revenue',
                                            data: @js($dailyValues),
                                            borderColor: '#10b981',
                                            backgroundColor: 'rgba(16,185,129,0.12)',
                                            fill: true, tension: 0.3, borderWidth: 2,
                                            pointRadius: 0, pointHoverRadius: 4,
                                        }]
                                    },
                                    options: {
                                        responsive: true, maintainAspectRatio: false,
                                        plugins: { legend: { display: false } },
                                        scales: {
                                            x: { grid: { display: false }, ticks: { color: '#9ca3af', maxRotation: 0, autoSkip: true } },
                                            y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.15)' }, ticks: { color: '#9ca3af' } }
                                        }
                                    }
                                });
                            }
                        }" class="mt-4 h-[280px]">
                        <canvas x-ref="canvas"></canvas>
                    </div>
                </div>

                <div class="pp-card p-5">
                    <h2 class="flex items-center text-md font-semibold text-neutral-900">
                        <x-heroicon-o-chart-bar class="mr-2 inline-block h-6 w-6 text-amber-500" />
                        <span>{{ __('Monthly revenue') }} <span class="font-normal text-neutral-400">{{ __('· last 6 months') }}</span></span>
                    </h2>
                    <div x-data="{
                            init() {
                                window.ppChart(this.$refs.canvas, {
                                    type: 'bar',
                                    data: {
                                        labels: @js($monthlyLabels),
                                        datasets: [{
                                            label: 'Revenue',
                                            data: @js($monthlyValues),
                                            backgroundColor: 'rgba(217,164,65,0.55)',
                                            borderColor: '#d9a441',
                                            borderWidth: 1, borderRadius: 6,
                                        }]
                                    },
                                    options: {
                                        responsive: true, maintainAspectRatio: false,
                                        plugins: { legend: { display: false } },
                                        scales: {
                                            x: { grid: { display: false }, ticks: { color: '#9ca3af', maxRotation: 0, autoSkip: true } },
                                            y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.15)' }, ticks: { color: '#9ca3af' } }
                                        }
                                    }
                                });
                            }
                        }" class="mt-4 h-[280px]">
                        <canvas x-ref="canvas"></canvas>
                    </div>
                </div>
            </div>
        @endif

        {{-- Withdraw modal --}}
        @if ($canWithdraw && $asset)
            <div x-show="showWithdraw" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="showWithdraw = false"></div>
                <div class="relative w-full max-w-md pp-card p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">{{ __('Withdraw :symbol revenue', ['symbol' => $asset->symbol]) }}</h3>
                        <button type="button" x-on:click="showWithdraw = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <p class="mb-4 text-sm text-neutral-500">{{ __('Available:') }} <span class="tabular font-semibold text-emerald-600">{{ $available }}</span></p>
                    <form method="POST" action="{{ route('admin.revenue-wallet.withdraw') }}" class="space-y-4">
                        @csrf
                        <x-ui.input :label="__('Amount')" name="amount" type="text" inputmode="decimal" placeholder="0.00" :value="old('amount')" :error="$errors->first('amount')" />
                        <x-ui.select :label="__('Network')" name="network" :error="$errors->first('network')">
                            @foreach ($chains as $chain)
                                @php $chainKey = is_string($chain->key) ? $chain->key : $chain->key->value; @endphp
                                <option value="{{ $chainKey }}" @selected(old('network') === $chainKey)>{{ $chain->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input :label="__('Destination address')" name="destination" :placeholder="__('0x… / wallet address')" :value="old('destination')" :error="$errors->first('destination')" />
                        <x-ui.textarea :label="__('Note (optional)')" name="note" :rows="2" :error="$errors->first('note')">{{ old('note') }}</x-ui.textarea>
                        <x-ui.input :label="__('Confirm your password')" name="password" type="password" placeholder="••••••••" :error="$errors->first('password')" />
                        <div class="flex justify-end gap-2 pt-1">
                            <x-ui.button type="button" variant="secondary" x-on:click="showWithdraw = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="submit" icon="banknotes">{{ __('Request withdrawal') }}</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.admin>
