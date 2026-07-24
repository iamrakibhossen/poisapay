<x-layouts.admin :title="__('Deposit Monitor')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Deposit Monitor')" :subtitle="__('On-chain deposit detection and confirmation progress.')" />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-ui.stat-card :label="__('Detected')" :value="$stats['detected']" icon="magnifying-glass-circle" accent="brand" />
            <x-ui.stat-card :label="__('Confirming')" :value="$stats['confirming']" icon="clock" accent="amber" />
            <x-ui.stat-card :label="__('Credited today')" :value="$stats['creditedToday']" icon="check-circle" accent="emerald" />
            <x-ui.stat-card :label="__('Orphaned')" :value="$stats['orphaned']" icon="exclamation-triangle" accent="rose" />
        </div>

        <div>
            <h3 class="mb-2 text-sm font-semibold text-neutral-700">{{ __('In-flight deposits') }}</h3>
            <x-ui.table :headers="[__('User'), __('Asset'), __('Amount'), __('Status'), __('Confirmations'), __('Detected')]">
                @forelse ($inflight as $deposit)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3">
                            <p class="truncate text-sm font-medium text-neutral-900">{{ $deposit->user?->name ?? '—' }}</p>
                            <p class="truncate text-xs text-neutral-500">{{ $deposit->user?->email }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <x-ui.asset-icon :symbol="$deposit->asset->symbol" size="sm" />
                                <span class="text-sm font-medium text-neutral-900">{{ $deposit->asset->symbol }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3"><span class="tabular text-sm font-semibold text-neutral-900">{{ $deposit->money()->format() }}</span></td>
                        <td class="px-4 py-3"><x-ui.badge :color="$deposit->status->color()" dot>{{ $deposit->status->label() }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-sm text-neutral-600"><span class="tabular">{{ $deposit->confirmations }} / {{ $deposit->required_confirmations }}</span></td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $deposit->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="check-circle" :title="__('No deposits in flight')" :description="__('All detected deposits are credited.')" /></td></tr>
                @endforelse
            </x-ui.table>
            {{ $inflight->links() }}
        </div>

        <div>
            <h3 class="mb-2 text-sm font-semibold text-neutral-700">{{ __('Recent on-chain transactions') }}</h3>
            <x-ui.table :headers="[__('Chain'), __('Asset'), __('Amount'), __('Status'), __('Confs'), __('Tx Hash'), __('Seen')]">
                @forelse ($recentTxs as $tx)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3 text-sm text-neutral-700">{{ $tx->chain?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-700">{{ $tx->asset?->symbol ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="tabular text-sm text-neutral-900">{{ $tx->asset ? $tx->asset->money($tx->amount)->format() : $tx->amount }}</span></td>
                        <td class="px-4 py-3"><x-ui.badge :color="$tx->status->color()" dot>{{ $tx->status->label() }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-sm text-neutral-600 tabular">{{ $tx->confirmations }}</td>
                        <td class="px-4 py-3"><span class="font-mono text-xs text-neutral-500">{{ Str::limit($tx->tx_hash, 16) }}</span></td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $tx->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="signal" :title="__('No on-chain activity')" :description="__('Observed transactions will appear here.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-layouts.admin>
