<x-layouts.admin :title="'Custody Wallets'">
    <div class="space-y-6">
        <x-ui.page-header title="Custody wallets" subtitle="Where platform funds live — the per-chain hot wallet that funds withdrawals, and cold storage held in reserve.">
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.blockchain-health') }}" variant="secondary" icon="signal" size="sm">Chain health</x-ui.button>
                <x-ui.button href="{{ route('admin.treasury') }}" variant="secondary" icon="building-library" size="sm">Solvency</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($simulated)
            <x-ui.alert type="info">
                Custody is running in <strong>simulated</strong> mode. Balances are ledger-backed and real; hot-wallet addresses derive from the demo seed. Set a KMS/HSM signer and <code>custody_simulated=false</code> for production.
            </x-ui.alert>
        @endif

        {{-- KPI strip --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat-card label="Chains" :value="$chainCount" icon="link" accent="brand" />
            <x-ui.stat-card label="Hot wallets" :value="$hotConfigured" icon="fire" accent="amber" />
            <x-ui.stat-card label="Cold-watch addresses" :value="$coldWatchCount" icon="lock-closed" accent="emerald" />
            <x-ui.stat-card label="Low gas" :value="$lowGasCount" icon="exclamation-triangle" :accent="$lowGasCount ? 'rose' : 'emerald'" />
        </div>

        @forelse ($wallets as $w)
            <x-ui.card class="p-0">
                {{-- Chain header --}}
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :name="$w['chain']->name" size="sm" />
                        <div>
                            <p class="font-semibold text-gray-900">{{ $w['chain']->name }}</p>
                            <p class="text-xs text-gray-500">Native {{ $w['gasSymbol'] }} · {{ $w['chain']->is_evm ? 'EVM' : 'Non-EVM' }}</p>
                        </div>
                    </div>
                    @if ($w['gasBalance'] !== null)
                        <div class="text-right">
                            <p class="text-[11px] uppercase tracking-wide text-gray-400">Gas wallet</p>
                            <p class="tabular text-sm font-semibold text-gray-900">
                                {{ $w['gasBalance'] }} {{ $w['gasSymbol'] }}
                                @if ($w['gasLow'])<x-ui.badge color="danger" dot>Low</x-ui.badge>@endif
                            </p>
                        </div>
                    @endif
                </div>

                <div class="grid gap-0 lg:grid-cols-2 lg:divide-x divide-gray-100">
                    {{-- HOT wallet --}}
                    <div class="p-5">
                        <div class="mb-3 flex items-center gap-2">
                            <x-heroicon-o-fire class="h-4 w-4 text-amber-500" />
                            <h3 class="text-sm font-semibold text-gray-900">Hot wallet</h3>
                            <span class="text-xs text-gray-400">funds withdrawals</span>
                        </div>

                        @if ($w['hotAddress'])
                            <div class="mb-4 flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                <span class="min-w-0 flex-1 truncate font-mono text-xs text-gray-700" title="{{ $w['hotAddress'] }}">{{ $w['hotAddress'] }}</span>
                                <x-ui.copy-text :text="$w['hotAddress']" label="Copy address" />
                                @if ($w['hotExplorer'])
                                    <a href="{{ $w['hotExplorer'] }}" target="_blank" rel="noopener" class="text-gray-500 hover:text-brand-600" title="View on explorer">
                                        <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                                    </a>
                                @endif
                            </div>
                        @else
                            <p class="mb-4 rounded-lg border border-dashed border-gray-200 px-3 py-2 text-xs text-gray-400">Address not derivable — custody signer not configured for this chain.</p>
                        @endif

                        @if (count($w['assets']))
                            <table class="min-w-full text-sm">
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($w['assets'] as $a)
                                        <tr>
                                            <td class="py-2 pr-3">
                                                <span class="font-medium text-gray-800">{{ $a['symbol'] }}</span>
                                                @if ($a['contract'])<span class="ml-1 font-mono text-[10px] text-gray-400">ERC-20</span>@endif
                                            </td>
                                            <td class="py-2 text-right tabular {{ $a['hotZero'] ? 'text-gray-400' : 'font-semibold text-gray-900' }}">{{ $a['hot'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-xs text-gray-400">No crypto assets on this chain.</p>
                        @endif
                    </div>

                    {{-- COLD storage --}}
                    <div class="p-5">
                        <div class="mb-3 flex items-center gap-2">
                            <x-heroicon-o-lock-closed class="h-4 w-4 text-emerald-500" />
                            <h3 class="text-sm font-semibold text-gray-900">Cold storage</h3>
                            <span class="text-xs text-gray-400">offline reserve</span>
                        </div>

                        @if ($w['coldWatch']->isNotEmpty())
                            <div class="mb-4 space-y-2">
                                @foreach ($w['coldWatch'] as $x)
                                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-xs font-medium text-gray-700">{{ $x->label }}</p>
                                            <p class="truncate font-mono text-[10px] text-gray-400" title="{{ $x->xpub }}">{{ Str::limit($x->xpub, 28) }} · {{ $x->derivation_path }}</p>
                                        </div>
                                        <x-ui.badge :color="$x->is_active ? 'success' : 'gray'" dot>{{ $x->is_active ? 'Watching' : 'Off' }}</x-ui.badge>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="mb-4 rounded-lg border border-dashed border-gray-200 px-3 py-2 text-xs text-gray-400">
                                No cold-watch address registered.
                                <a href="{{ route('admin.custody') }}" class="text-brand-600 hover:underline">Add one →</a>
                            </p>
                        @endif

                        @if (count($w['assets']))
                            <table class="min-w-full text-sm">
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($w['assets'] as $a)
                                        <tr>
                                            <td class="py-2 pr-3 font-medium text-gray-800">{{ $a['symbol'] }}</td>
                                            <td class="py-2 text-right tabular {{ $a['coldZero'] ? 'text-gray-400' : 'font-semibold text-gray-900' }}">{{ $a['cold'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.card>
                <x-ui.empty-state icon="wallet" title="No active chains" description="Enable a chain to see its custody wallets." />
            </x-ui.card>
        @endforelse
    </div>
</x-layouts.admin>
