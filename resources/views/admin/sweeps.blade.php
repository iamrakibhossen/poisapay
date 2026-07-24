<x-layouts.admin :title="__('Sweep Queue')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Sweep Queue')" :subtitle="__('Custody sweeps moving deposit-address funds into treasury.')" />

        <form method="GET" action="{{ route('admin.sweeps') }}">
            <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
                @foreach ($tabs as $key => $count)
                    <a href="{{ route('admin.sweeps', ['status' => $key]) }}"
                        @class([
                            'flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium capitalize transition',
                            'bg-white text-neutral-900 shadow-sm' => $status === $key,
                            'text-neutral-500 hover:text-neutral-800' => $status !== $key,
                        ])>
                        {{ $key }}
                        <span class="rounded-full bg-neutral-200 px-1.5 text-xs">{{ $count }}</span>
                    </a>
                @endforeach
            </div>
        </form>

        <x-ui.table :headers="[__('Asset'), __('Amount'), __('Gas Cost'), __('Status'), __('Tx'), __('Created')]">
            @forelse ($sweeps as $sweep)
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <x-ui.asset-icon :symbol="$sweep->asset->symbol" size="sm" />
                            <span class="text-sm font-medium text-neutral-900">{{ $sweep->asset->symbol }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3"><span class="tabular text-sm font-semibold text-neutral-900">{{ $sweep->asset->money($sweep->amount)->format() }}</span></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $sweep->asset->money($sweep->gas_cost)->format() }}</td>
                    <td class="px-4 py-3"><x-ui.badge :color="$sweep->status->color()" dot>{{ $sweep->status->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3">
                        @if ($tx = $sweep->onchainTx)
                            <span class="font-mono text-xs text-neutral-500">{{ Str::limit($tx->tx_hash, 16) }}</span>
                        @else
                            <span class="text-neutral-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $sweep->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state icon="arrows-pointing-in" :title="__('No sweeps')" :description="__('Sweeps appear here once custody balances move into treasury.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $sweeps->links() }}
    </div>
</x-layouts.admin>
