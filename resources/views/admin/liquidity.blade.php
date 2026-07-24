<x-layouts.admin :title="__('Liquidity')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Liquidity')" :subtitle="__('Treasury reserve backing each asset — hot, cold and pending balances from the ledger.')" />

        <x-ui.table :headers="[__('Asset'), __('Chain'), __('Hot'), __('Cold'), __('Pending'), __('Total Reserve')]">
            @forelse ($rows as $row)
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <x-ui.asset-icon :symbol="$row['symbol']" size="sm" />
                            <span class="text-sm font-medium text-neutral-900">{{ $row['symbol'] }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $row['chain'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-700 tabular">{{ $row['hot'] }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-700 tabular">{{ $row['cold'] }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-700 tabular">{{ $row['pending'] }}</td>
                    <td class="px-4 py-3 text-sm font-semibold tabular {{ $row['zero'] ? 'text-neutral-400' : 'text-neutral-900' }}">{{ $row['total'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state icon="beaker" :title="__('No assets')" :description="__('Activate a crypto asset to see its reserve.')" /></td></tr>
            @endforelse
        </x-ui.table>
    </div>
</x-layouts.admin>
