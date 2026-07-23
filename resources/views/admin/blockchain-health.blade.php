<x-layouts.admin :title="__('Blockchain Health')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Blockchain Health')"
            :subtitle="__('Unified operational view of the (simulated) chain infrastructure — RPCs, custody, gas, and reconciliation.')">
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.blockchain-health.check') }}"
                    onsubmit="return confirm('{{ __('Probe all RPC endpoints now?') }}')">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm" icon="signal">{{ __('Run health check') }}</x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.blockchain-health.tick') }}"
                    onsubmit="return confirm('{{ __('Advance simulated chain state now?') }}')">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm" icon="forward">{{ __('Run monitor tick') }}</x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.blockchain-health.reconcile') }}"
                    onsubmit="return confirm('{{ __('Run reconciliation across all active assets now?') }}')">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm" icon="scale">{{ __('Run reconciliation') }}</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- KPI strip --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <x-ui.stat-card :label="__('Active chains')" :value="$totalChains" icon="link" accent="brand" />
            <x-ui.stat-card :label="__('RPCs up')" :value="$rpcUp.' / '.$rpcTotal" icon="signal"
                :accent="$rpcTotal > 0 && $rpcUp === $rpcTotal ? 'emerald' : ($rpcUp === 0 ? 'rose' : 'amber')" />
            <x-ui.stat-card :label="__('Pending deposits')" :value="$pendingDeposits" icon="arrow-down-tray" accent="brand" />
            <x-ui.stat-card :label="__('Pending sweeps')" :value="$pendingSweeps" icon="arrows-right-left" accent="brand" />
            <x-ui.stat-card :label="__('Gas-low warnings')" :value="$gasLowCount" icon="exclamation-triangle"
                :accent="$gasLowCount > 0 ? 'rose' : 'emerald'" />
        </div>

        {{-- Per-chain cards --}}
        @if ($summary->isEmpty())
            <x-ui.card><x-ui.empty-state icon="link-slash" :title="__('No active chains')"
                    :description="__('Enable a chain to monitor its blockchain health.')" /></x-ui.card>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($summary as $row)
                    @php
                        $chain = $row['chain'];
                        $rpcColor = $row['rpc_total'] > 0 && $row['rpc_up'] === $row['rpc_total']
                            ? 'success'
                            : ($row['rpc_up'] === 0 ? 'danger' : 'warning');
                        $recon = $row['reconciliation'];
                    @endphp
                    <div class="pp-card space-y-4 p-5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-semibold text-neutral-900">{{ $chain->name }}</h3>
                                <p class="text-xs text-neutral-500">{{ $chain->native_symbol }} · {{ __('min') }} {{ $chain->min_confirmations }} {{ __('conf') }}</p>
                            </div>
                            <x-ui.badge :color="$chain->key->color()">{{ $chain->key->label() }}</x-ui.badge>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-neutral-500">{{ __('RPC status') }}</span>
                            <x-ui.badge :color="$rpcColor" dot>{{ $row['rpc_up'] }}/{{ $row['rpc_total'] }} {{ __('up') }}</x-ui.badge>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-neutral-500">{{ __('Block tip') }}</span>
                            <span class="tabular font-semibold text-neutral-900">{{ $row['tip'] ? number_format($row['tip']) : '—' }}</span>
                        </div>

                        <dl class="space-y-2 border-t border-neutral-100 pt-3 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-neutral-500">{{ __('Hot wallet') }}</dt>
                                <dd class="tabular font-semibold text-neutral-900">{{ $row['hot']?->format() ?? '—' }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-neutral-500">{{ __('Cold wallet') }}</dt>
                                <dd class="tabular font-semibold text-neutral-900">{{ $row['cold']?->format() ?? '—' }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-neutral-500">{{ __('Gas') }}</dt>
                                <dd class="flex items-center gap-2">
                                    <span class="tabular font-semibold text-neutral-900">{{ $row['gas']?->money()?->format() ?? '—' }}</span>
                                    @if ($row['gas_low'])
                                        <x-ui.badge color="danger">{{ __('Low') }}</x-ui.badge>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <div class="flex items-center justify-between border-t border-neutral-100 pt-3 text-sm">
                            <span class="text-neutral-500">{{ __('Pending') }}</span>
                            <span class="text-neutral-700">
                                <span class="tabular font-semibold text-neutral-900">{{ $row['pending_deposits'] }}</span> {{ __('dep') }} ·
                                <span class="tabular font-semibold text-neutral-900">{{ $row['pending_sweeps'] }}</span> {{ __('sweep') }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between border-t border-neutral-100 pt-3 text-sm">
                            <span class="text-neutral-500">{{ __('Reconciliation') }}</span>
                            @if ($recon)
                                <div class="flex items-center gap-2">
                                    <x-ui.badge :color="$recon->is_solvent ? 'success' : 'danger'" dot>{{ $recon->is_solvent ? __('Solvent') : __('Insolvent') }}</x-ui.badge>
                                    <span class="text-xs text-neutral-400">{{ $recon->created_at->diffForHumans() }}</span>
                                </div>
                            @else
                                <x-ui.badge color="gray">{{ __('No runs') }}</x-ui.badge>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- RPC endpoints table --}}
        <div class="space-y-3">
            <div>
                <h3 class="text-base font-semibold text-neutral-900">{{ __('RPC endpoints') }}</h3>
                <p class="mt-0.5 text-sm text-neutral-500">{{ __('Health across all configured nodes') }}</p>
            </div>
            <x-ui.table :headers="[__('Chain'), __('Name'), __('URL'), __('Status'), __('Last block'), __('Latency'), __('Last checked')]">
                @forelse ($rpcs as $rpc)
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="px-3 py-3">
                            @if ($rpc->chain)
                                <x-ui.badge :color="$rpc->chain->key->color()">{{ $rpc->chain->key->label() }}</x-ui.badge>
                            @else
                                <span class="text-sm text-neutral-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3"><span class="text-sm font-medium text-neutral-900">{{ $rpc->name }}</span></td>
                        <td class="px-3 py-3"><span class="block max-w-[16rem] truncate font-mono text-xs text-neutral-500" title="{{ $rpc->url }}">{{ $rpc->url }}</span></td>
                        <td class="px-3 py-3"><x-ui.badge :color="$rpc->statusColor()" dot>{{ ucfirst($rpc->status) }}</x-ui.badge></td>
                        <td class="px-3 py-3"><span class="tabular text-sm text-neutral-700">{{ $rpc->last_block ? number_format($rpc->last_block) : '—' }}</span></td>
                        <td class="px-3 py-3"><span class="tabular text-sm text-neutral-700">{{ $rpc->latency_ms !== null ? $rpc->latency_ms.' ms' : '—' }}</span></td>
                        <td class="px-3 py-3"><span class="text-sm text-neutral-500">{{ $rpc->last_checked_at?->diffForHumans() ?? __('Never') }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="signal-slash" :title="__('No RPC endpoints')" :description="__('Configure RPC endpoints to monitor chain connectivity.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>

        {{-- Recent sweeps table --}}
        <div class="space-y-3">
            <div>
                <h3 class="text-base font-semibold text-neutral-900">{{ __('Recent sweeps') }}</h3>
                <p class="mt-0.5 text-sm text-neutral-500">{{ __('Latest custody sweeps into treasury') }}</p>
            </div>
            <x-ui.table :headers="[__('Asset'), __('Amount'), __('Gas cost'), __('Status'), __('When')]">
                @forelse ($recentSweeps as $sweep)
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-2">
                                <x-ui.asset-icon :symbol="$sweep->asset->symbol" size="sm" />
                                <span class="text-sm font-medium text-neutral-900">{{ $sweep->asset->symbol }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-3"><span class="tabular text-sm font-medium text-neutral-900">{{ $sweep->asset->money($sweep->amount)->format() }}</span></td>
                        <td class="px-3 py-3"><span class="tabular text-sm text-neutral-600">{{ $sweep->gas_cost !== null ? $sweep->asset->money($sweep->gas_cost)->format() : '—' }}</span></td>
                        <td class="px-3 py-3"><x-ui.badge :color="$sweep->status->color()" dot>{{ $sweep->status->label() }}</x-ui.badge></td>
                        <td class="px-3 py-3"><span class="text-sm text-neutral-500">{{ $sweep->created_at->diffForHumans() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state icon="arrows-right-left" :title="__('No sweeps yet')" :description="__('Sweeps appear here once custody balances are moved into treasury.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-layouts.admin>
