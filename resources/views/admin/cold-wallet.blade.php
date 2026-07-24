<x-layouts.admin :title="__('Cold Wallet')">
    @php
        $refillColor = ['requested' => 'warning', 'approved' => 'info', 'broadcast' => 'info', 'settled' => 'success', 'cancelled' => 'gray'];
    @endphp
    <div class="space-y-6">
        <x-ui.page-header :title="__('Cold Wallet')" :subtitle="__('Watch-only cold storage reserves and cold→hot refill requests.')" />

        <div class="grid grid-cols-2 gap-4">
            <x-ui.stat-card :label="__('Chains')" :value="$chainCount" icon="signal" accent="brand" />
            <x-ui.stat-card :label="__('Cold-watch addresses')" :value="$coldWatchCount" icon="lock-closed" accent="emerald" />
        </div>

        @forelse ($wallets as $w)
            <x-ui.card>
                <div class="flex items-center gap-2 border-b border-gray-100 pb-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ $w['chain']->name }}</h3>
                    <x-ui.badge color="emerald">{{ __('Cold storage') }}</x-ui.badge>
                </div>

                <div class="pt-3">
                    <div class="mb-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($w['assets'] as $a)
                            <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2">
                                <span class="text-sm font-medium text-neutral-700">{{ $a['symbol'] }}</span>
                                <span class="tabular text-sm font-semibold {{ $a['zero'] ? 'text-neutral-400' : 'text-neutral-900' }}">{{ $a['cold'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    @forelse ($w['coldWatch'] as $x)
                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-50 py-1.5 text-xs">
                            <span class="font-medium text-neutral-700">{{ $x->label }}</span>
                            <span class="font-mono text-neutral-400">{{ Str::limit($x->xpub, 24) }}</span>
                            @unless ($x->is_active)<x-ui.badge color="gray">{{ __('Inactive') }}</x-ui.badge>@endunless
                        </div>
                    @empty
                        <p class="text-xs text-neutral-400">{{ __('No cold-watch addresses registered.') }}</p>
                    @endforelse
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty-state icon="lock-closed" :title="__('No active chains')" :description="__('Activate a chain to see its cold storage.')" />
        @endforelse

        <div>
            <h3 class="mb-2 text-sm font-semibold text-neutral-700">{{ __('Cold → hot refill requests') }}</h3>
            <x-ui.table :headers="[__('Chain'), __('Asset'), __('Amount'), __('Status'), __('Tx'), __('Requested')]">
                @forelse ($refills as $r)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3 text-sm text-neutral-700">{{ $chainNames[$r->chain_id] ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-700">{{ $assetSymbols[$r->asset_id] ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-900 tabular">{{ $r->amount }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$refillColor[$r->status] ?? 'gray'" dot>{{ ucfirst($r->status) }}</x-ui.badge></td>
                        <td class="px-4 py-3"><span class="font-mono text-xs text-neutral-500">{{ $r->tx_hash ? Str::limit($r->tx_hash, 16) : '—' }}</span></td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $r->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="arrows-up-down" :title="__('No refill requests')" :description="__('Cold→hot refills appear here when hot reserves run low.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-layouts.admin>
