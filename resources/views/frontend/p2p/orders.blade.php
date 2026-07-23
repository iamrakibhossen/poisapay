<x-layouts.app :title="'My P2P orders'">
    <div class="space-y-6">
        <x-ui.page-header title="My orders" subtitle="Your P2P trades.">
            <x-slot:actions>
                <a href="{{ route('p2p') }}"><x-ui.button variant="secondary" icon="arrow-left">Marketplace</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card>
            <x-ui.table :headers="['Order', 'Role', 'Amount', 'Fiat', 'Status', '']">
                @forelse ($orders as $order)
                    <tr class="border-b border-neutral-100">
                        <td class="px-3 py-3 font-mono text-xs text-neutral-500">{{ $order->ref }}</td>
                        <td class="px-3 py-3"><x-ui.badge :color="$order->buyer_id === $me ? 'success' : 'info'">{{ $order->buyer_id === $me ? 'Buying' : 'Selling' }}</x-ui.badge></td>
                        <td class="px-3 py-3 text-sm text-neutral-900 tabular">{{ $order->cryptoMoney()->format() }}</td>
                        <td class="px-3 py-3 text-sm text-neutral-600 tabular">{{ number_format((float) $order->fiat_amount, 2) }} {{ $order->fiat_currency }}</td>
                        <td class="px-3 py-3"><x-ui.badge :color="$order->status->color()" dot>{{ $order->status->label() }}</x-ui.badge></td>
                        <td class="px-3 py-3 text-right"><a href="{{ route('p2p.order', $order) }}"><x-ui.button size="sm" variant="secondary" icon="eye">Open</x-ui.button></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="clock" title="No orders" description="Open a trade from the marketplace." /></td></tr>
                @endforelse
            </x-ui.table>
            <div class="mt-4">{{ $orders->links() }}</div>
        </x-ui.card>
    </div>
</x-layouts.app>
