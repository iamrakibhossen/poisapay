<x-layouts.admin :title="__('Revenue')">
    <div class="space-y-6"
        x-data="{
            tab: '{{ old('revwd_id') ? 'approvals' : 'payouts' }}',
            showWithdraw: {{ $errors->any() && ! old('revwd_id') ? 'true' : 'false' }},
            mode: '{{ $errors->has('password') && ! old('revwd_id') ? 'request' : 'instant' }}',
            assetId: {{ old('asset_id') ?: 'null' }}, assetSymbol: '', available: '', isFiat: false,
            openWithdraw(id, symbol, available, isFiat) { this.assetId = id; this.assetSymbol = symbol; this.available = available; this.isFiat = isFiat; this.mode = 'instant'; this.showWithdraw = true; },
            closeWithdraw() { this.showWithdraw = false; },
            approveId: {{ old('revwd_id') ? \Illuminate\Support\Js::from(old('revwd_id')) : 'null' }}, approveRef: '',
            openApprove(id, ref) { this.approveId = id; this.approveRef = ref; },
            closeApprove() { this.approveId = null; },
        }"
    >
        <x-ui.page-header :title="__('Revenue')" :subtitle="__('Your platform earnings — fees, spread and card revenue. Separate from user funds.')" />

        <x-ui.alert type="info">
            {{ __('Withdrawing profit moves the backing crypto out of the treasury and broadcasts it (simulated on testnet) with a tx hash.') }}
            {{ __('Use') }} <span class="font-semibold">{{ __('Request approval') }}</span> {{ __('for a second operator to sign off on large payouts.') }}
        </x-ui.alert>

        {{-- Earnings dashboard (primary asset) --}}
        @if ($stats)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-ui.stat-card :label="__('Today (:symbol)', ['symbol' => $primarySymbol])" :value="$stats['today']->format()" icon="sun" accent="emerald" />
                <x-ui.stat-card :label="__('This week')" :value="$stats['week']->format()" icon="calendar-days" accent="brand" />
                <x-ui.stat-card :label="__('This month')" :value="$stats['month']->format()" icon="calendar" accent="amber" />
                <x-ui.stat-card :label="__('Lifetime')" :value="$stats['lifetime']->format()" icon="banknotes" accent="emerald" />
            </div>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div class="pp-card p-5">
                    <h2 class="mb-1 text-sm font-semibold text-neutral-900">{{ __('Daily revenue') }} <span class="font-normal text-neutral-400">· {{ $primarySymbol }} · {{ __('14 days') }}</span></h2>
                    <div x-data="{ init() { window.ppChart(this.$refs.canvas, { type: 'line', data: { labels: @js($dailyLabels), datasets: [{ label: 'Revenue', data: @js($dailyValues), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.12)', fill: true, tension: 0.3, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: '#9ca3af', maxRotation: 0, autoSkip: true } }, y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.15)' }, ticks: { color: '#9ca3af' } } } } }); } }" class="mt-2 h-[240px]">
                        <canvas x-ref="canvas"></canvas>
                    </div>
                </div>
                <div class="pp-card p-5">
                    <h2 class="mb-1 text-sm font-semibold text-neutral-900">{{ __('Monthly revenue') }} <span class="font-normal text-neutral-400">· {{ $primarySymbol }} · {{ __('6 months') }}</span></h2>
                    <div x-data="{ init() { window.ppChart(this.$refs.canvas, { type: 'bar', data: { labels: @js($monthlyLabels), datasets: [{ label: 'Revenue', data: @js($monthlyValues), backgroundColor: 'rgba(217,164,65,0.55)', borderColor: '#d9a441', borderWidth: 1, borderRadius: 6 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: '#9ca3af' } }, y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.15)' }, ticks: { color: '#9ca3af' } } } } }); } }" class="mt-2 h-[240px]">
                        <canvas x-ref="canvas"></canvas>
                    </div>
                </div>
            </div>
        @endif

        {{-- Profit by coin --}}
        <div class="space-y-3">
            <h3 class="text-base font-semibold text-neutral-900">{{ __('Profit by coin') }}</h3>
            <x-ui.table :headers="[__('Coin / network'), __('Available profit'), __('Total withdrawn'), '']">
                @forelse ($coins as $coin)
                    <tr class="border-t border-neutral-200 bg-neutral-50/70">
                        <td class="px-4 py-2.5" colspan="2">
                            <div class="flex items-center gap-2.5">
                                <x-ui.asset-icon :symbol="$coin['symbol']" size="sm" />
                                <span class="text-sm font-bold text-neutral-900">{{ $coin['symbol'] }}</span>
                                <span class="text-sm text-neutral-500">{{ $coin['name'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 tabular text-sm font-bold text-emerald-600">{{ $coin['available'] }}</td>
                        <td class="px-4 py-2.5 tabular text-right text-sm text-neutral-500">{{ $coin['withdrawn'] }} {{ __('withdrawn') }}</td>
                    </tr>
                    @foreach ($coin['networks'] as $n)
                        <tr class="hover:bg-neutral-50">
                            <td class="py-3 pl-10 pr-4">
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span>
                                    <span class="text-sm text-neutral-700">{{ $n['network'] }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 tabular text-sm font-semibold {{ $n['availablePositive'] ? 'text-emerald-600' : 'text-neutral-400' }}">{{ $n['available'] }}</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-right">
                                @if ($canWithdraw && $n['availablePositive'])
                                    <x-ui.button type="button" size="sm" icon="banknotes"
                                        x-on:click="openWithdraw({{ $n['id'] }}, {{ \Illuminate\Support\Js::from($coin['symbol']) }}, {{ \Illuminate\Support\Js::from($n['available']) }}, {{ $coin['isFiat'] ? 'true' : 'false' }})">{{ __('Withdraw') }}</x-ui.button>
                                @else
                                    <span class="text-xs text-neutral-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="4"><x-ui.empty-state icon="banknotes" :title="__('No profit yet')"
                        :description="__('Fees from swaps, cards, deposits and withdrawals will show up here as revenue.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>

        {{-- Tabs: payouts / fee transactions / pending approvals --}}
        <div>
            <div class="mb-3 flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
                @php
                    $tabs = ['payouts' => __('Payout history'), 'transactions' => __('Fee transactions'), 'approvals' => __('Pending approvals')];
                @endphp
                @foreach ($tabs as $key => $label)
                    <button type="button" x-on:click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-500 hover:text-neutral-800'"
                        class="flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition">
                        {{ $label }}
                        @if ($key === 'approvals' && $pendingCount > 0)
                            <span class="rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-amber-700">{{ $pendingCount }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Payout history --}}
            <div x-show="tab === 'payouts'" x-cloak>
                <x-ui.table :headers="[__('Amount'), __('Asset'), __('Network'), __('Destination'), __('Tx'), __('Status'), __('By'), __('When')]">
                    @forelse ($payouts as $payout)
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="px-4 py-3 tabular text-sm font-semibold text-neutral-900">{{ $payout->money()->format() }}</td>
                            <td class="px-4 py-3 text-sm text-neutral-600">{{ $payout->asset?->symbol }}</td>
                            <td class="px-4 py-3 text-sm text-neutral-600">{{ $payout->network ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-neutral-600"><span class="font-mono text-xs">{{ $payout->destination_address ?: ($payout->destination ?: '—') }}</span></td>
                            <td class="px-4 py-3 text-sm">
                                @if ($payout->tx_hash)
                                    <span class="font-mono text-xs text-neutral-600" title="{{ $payout->tx_hash }}">{{ $payout->shortTxHash() }}</span>
                                @else
                                    <span class="text-neutral-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-ui.badge :color="$payout->statusColor()" dot>{{ ucfirst($payout->status ?? 'completed') }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-sm text-neutral-500">{{ $payout->operator?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-neutral-500">{{ $payout->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-ui.empty-state icon="clock" :title="__('No payouts yet')" :description="__('Recorded profit withdrawals will appear here.')" /></td></tr>
                    @endforelse
                </x-ui.table>
            </div>

            {{-- Fee transactions --}}
            <div x-show="tab === 'transactions'" x-cloak>
                <div class="mb-2 flex justify-end">
                    <x-ui.button href="{{ route('admin.revenue-transactions.export') }}" variant="secondary" size="sm" icon="arrow-down-tray">{{ __('Export CSV') }}</x-ui.button>
                </div>
                <x-ui.table :headers="[__('Fee type'), __('Source'), __('User'), __('Amount'), __('When')]">
                    @forelse ($transactions as $row)
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="px-4 py-3 text-sm font-medium text-neutral-800">{{ $feeTypeLabel($row->account_type, $row->entry_type) }}</td>
                            <td class="px-4 py-3 text-sm text-neutral-500">{{ \Illuminate\Support\Str::headline((string) $row->entry_type) }}</td>
                            <td class="px-4 py-3 text-sm text-neutral-600">{{ $row->user_name ?? __('System') }}</td>
                            <td class="px-4 py-3 tabular text-sm font-semibold text-emerald-600">+{{ \App\Support\Money::ofBase($row->amount, $row->decimals, $row->symbol)->format() }}</td>
                            <td class="px-4 py-3 text-xs text-neutral-500">{{ \Illuminate\Support\Carbon::parse($row->created_at)->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty-state icon="receipt-percent" :title="__('No fee income yet')" :description="__('Every fee credit will be listed here.')" /></td></tr>
                    @endforelse
                </x-ui.table>
            </div>

            {{-- Pending approvals (2-operator withdrawals) --}}
            <div x-show="tab === 'approvals'" x-cloak>
                <x-ui.table :headers="[__('Amount'), __('Asset'), __('Network'), __('Destination'), __('Status'), __('Requested by'), '']">
                    @forelse ($approvals as $w)
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="px-4 py-3 tabular text-sm font-semibold text-neutral-900">{{ $w->money()->format() }}</td>
                            <td class="px-4 py-3 text-sm text-neutral-600">{{ $w->asset?->symbol }}</td>
                            <td class="px-4 py-3 text-sm text-neutral-600">{{ $w->network ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-neutral-600"><span class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($w->destination_address, 18) }}</span></td>
                            <td class="px-4 py-3"><x-ui.badge :color="$w->status->color()" dot>{{ $w->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-sm text-neutral-500">{{ $w->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($canApprove && $w->status === \App\Enums\RevenueWithdrawalStatus::Pending)
                                    <x-ui.button type="button" variant="success" size="sm" icon="check"
                                        x-on:click="openApprove({{ \Illuminate\Support\Js::from((string) $w->id) }}, {{ \Illuminate\Support\Js::from($w->money()->format()) }})">{{ __('Approve') }}</x-ui.button>
                                @else
                                    <span class="text-xs text-neutral-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state icon="shield-check" :title="__('No withdrawal requests')" :description="__('Approval-required payouts will appear here.')" /></td></tr>
                    @endforelse
                </x-ui.table>
            </div>
        </div>

        {{-- Withdraw modal (instant OR request approval) --}}
        @if ($canWithdraw || $canRequest)
            <div x-show="showWithdraw" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="closeWithdraw()"></div>
                <div class="relative w-full max-w-md pp-card p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">{{ __('Withdraw') }} <span x-text="assetSymbol"></span> {{ __('profit') }}</h3>
                        <button type="button" x-on:click="closeWithdraw()" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <p class="mb-4 text-sm text-neutral-500">{{ __('Available') }}: <span class="tabular font-semibold text-emerald-600" x-text="available"></span></p>

                    <p x-show="isFiat" x-cloak class="mb-4 rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-500">
                        <span x-text="assetSymbol"></span> {{ __('is fiat — there\'s no blockchain send. This') }} <span class="font-medium">{{ __('records') }}</span> {{ __('the payout from your business float; move the cash to your bank yourself.') }}
                    </p>

                    {{-- Mode toggle (approval flow is on-chain — crypto only) --}}
                    @if ($canWithdraw && $canRequest)
                        <div class="mb-4 grid grid-cols-2 gap-2" x-show="! isFiat" x-cloak>
                            <button type="button" x-on:click="mode = 'instant'"
                                :class="mode === 'instant' ? 'border-brand-500 bg-brand-50 text-brand-700 ring-1 ring-brand-500' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50'"
                                class="rounded-lg border px-3 py-2 text-sm font-semibold transition">{{ __('Instant') }}</button>
                            <button type="button" x-on:click="mode = 'request'"
                                :class="mode === 'request' ? 'border-brand-500 bg-brand-50 text-brand-700 ring-1 ring-brand-500' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50'"
                                class="rounded-lg border px-3 py-2 text-sm font-semibold transition">{{ __('Request approval') }}</button>
                        </div>
                    @endif

                    {{-- Instant payout --}}
                    @if ($canWithdraw)
                        <form method="POST" action="{{ route('admin.revenue.withdraw') }}" class="space-y-4" x-show="mode === 'instant'">
                            @csrf
                            <input type="hidden" name="asset_id" x-bind:value="assetId">
                            <x-ui.input :label="__('Amount')" name="amount" type="text" inputmode="decimal" placeholder="0.00" :value="old('amount')" :error="$errors->first('amount')" />
                            <div>
                                <label class="pp-label" for="rev-destination"><span x-text="isFiat ? 'Payout reference (bank / mobile)' : 'Destination address'"></span></label>
                                <x-ui.input id="rev-destination" name="destination" :value="old('destination')" :error="$errors->first('destination')"
                                    x-bind:placeholder="isFiat ? 'e.g. Company bank account' : 'Wallet / exchange deposit address'" />
                                <p x-show="! isFiat" x-cloak class="mt-1 text-xs text-neutral-400">{{ __('Broadcasts on the coin\'s chain (simulated on testnet) and gets a tx hash.') }}</p>
                            </div>
                            <x-ui.textarea :label="__('Note (optional)')" name="note" :rows="2" :error="$errors->first('note')">{{ old('note') }}</x-ui.textarea>
                            <div class="flex justify-end gap-2 pt-1">
                                <x-ui.button type="button" variant="secondary" x-on:click="closeWithdraw()">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="submit" icon="banknotes"><span x-text="isFiat ? 'Record payout' : 'Withdraw now'"></span></x-ui.button>
                            </div>
                        </form>
                    @endif

                    {{-- Request approval (2-operator, on-chain — crypto only) --}}
                    @if ($canRequest)
                        <form method="POST" action="{{ route('admin.revenue-wallet.withdraw') }}" class="space-y-4" x-show="mode === 'request' && ! isFiat">
                            @csrf
                            <input type="hidden" name="asset_id" x-bind:value="assetId">
                            <x-ui.input :label="__('Amount')" name="amount" type="text" inputmode="decimal" placeholder="0.00" :value="old('amount')" :error="$errors->first('amount')" />
                            <x-ui.input :label="__('Destination address')" name="destination" :placeholder="__('Wallet / exchange deposit address')" :value="old('destination')" :error="$errors->first('destination')" />
                            <x-ui.input :label="__('Confirm your password')" name="password" type="password" placeholder="••••••••" :error="$errors->first('password')" />
                            <x-ui.textarea :label="__('Note (optional)')" name="note" :rows="2" :error="$errors->first('note')">{{ old('note') }}</x-ui.textarea>
                            <p class="rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-500">{{ __('Creates a pending request; a second operator approves it under Pending approvals.') }}</p>
                            <div class="flex justify-end gap-2 pt-1">
                                <x-ui.button type="button" variant="secondary" x-on:click="closeWithdraw()">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="submit" icon="shield-check">{{ __('Request withdrawal') }}</x-ui.button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- Approve modal --}}
        @if ($canApprove)
            <div x-show="approveId !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="closeApprove()"></div>
                <div class="relative w-full max-w-md pp-card p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">{{ __('Approve payout') }} <span class="tabular" x-text="approveRef"></span></h3>
                        <button type="button" x-on:click="closeApprove()" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <form method="POST" x-bind:action="'{{ url('admin/finance/revenue-withdrawals') }}/' + approveId + '/approve'" class="space-y-4">
                        @csrf
                        <input type="hidden" name="revwd_id" x-bind:value="approveId">
                        <p class="text-sm text-neutral-500">{{ __('Approving posts the ledger move, sends the crypto out of the treasury and broadcasts it.') }}</p>
                        <x-ui.input :label="__('Confirm your password')" name="password" type="password" placeholder="••••••••" :error="$errors->first('password')" />
                        <div class="flex justify-end gap-2 pt-1">
                            <x-ui.button type="button" variant="secondary" x-on:click="closeApprove()">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="submit" variant="success" icon="check">{{ __('Approve & send') }}</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.admin>
