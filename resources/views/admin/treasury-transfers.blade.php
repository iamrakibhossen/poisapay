<x-layouts.admin :title="__('Treasury Transfers')">
    @php
        $moveColor = ['broadcast' => 'info', 'settled' => 'success', 'failed' => 'danger'];
        $refillColor = ['requested' => 'warning', 'approved' => 'info', 'broadcast' => 'info', 'settled' => 'success', 'cancelled' => 'gray'];
    @endphp
    <div class="space-y-6">
        <x-ui.page-header :title="__('Treasury Transfers')" :subtitle="__('Hot↔cold rebalancing moves and cold→hot refill requests.')" />

        <div>
            <h3 class="mb-2 text-sm font-semibold text-neutral-700">{{ __('Treasury moves') }}</h3>
            <x-ui.table :headers="[__('Chain'), __('Asset'), __('Direction'), __('Amount'), __('Status'), __('Tx'), __('When')]">
                @forelse ($moves as $m)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3 text-sm text-neutral-700">{{ $chainNames[$m->chain_id] ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-700">{{ $assetSymbols[$m->asset_id] ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600">{{ str_replace('_', ' → ', $m->direction) }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-900 tabular">{{ $m->amount }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$moveColor[$m->status] ?? 'gray'" dot>{{ ucfirst($m->status) }}</x-ui.badge></td>
                        <td class="px-4 py-3"><span class="font-mono text-xs text-neutral-500">{{ $m->onchain_tx_id ? Str::limit($m->onchain_tx_id, 12) : '—' }}</span></td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $m->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="arrows-up-down" :title="__('No treasury moves')" :description="__('Hot↔cold rebalancing moves appear here.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>

        <div>
            <h3 class="mb-2 text-sm font-semibold text-neutral-700">{{ __('Cold → hot refill requests') }}</h3>
            <x-ui.table :headers="[__('Chain'), __('Asset'), __('Amount'), __('Status'), __('Tx'), __('When')]">
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
