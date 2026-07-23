<x-layouts.admin :title="__('P2P Orders')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('P2P Orders')" :subtitle="__('Monitor peer-to-peer trades. Escrow settles on the ledger; disputes are adjudicated separately.')" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card :label="__('Open orders')" :value="number_format($stats['open'])" icon="clock" accent="brand" />
            <x-ui.stat-card :label="__('Disputed')" :value="number_format($stats['disputed'])" icon="scale" accent="amber" />
            <x-ui.stat-card :label="__('Completed')" :value="number_format($stats['completed'])" icon="check-circle" accent="emerald" />
            <x-ui.stat-card :label="__('Fee income (USDT)')" :value="$stats['fee_income']" icon="banknotes" accent="brand" />
        </div>

        <form method="GET" action="{{ route('admin.p2p') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-ui.select name="status" class="w-auto" onchange="this.form.submit()">
                <option value="all" @selected($status === 'all')>{{ __('All statuses') }}</option>
                @foreach (\App\Enums\P2pOrderStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.input name="search" :value="$search" icon="magnifying-glass" placeholder="{{ __('Search by order ref…') }}" class="w-full sm:w-72" />
        </form>

        <x-ui.card>
            <x-ui.table :headers="[__('Order'), __('Buyer'), __('Seller'), __('Amount'), __('Fiat'), __('Status'), __('Opened')]">
                @forelse ($orders as $order)
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="px-3 py-3 font-mono text-xs text-neutral-500">{{ $order->ref }}</td>
                        <td class="px-3 py-3 text-sm text-neutral-800">{{ $order->buyer?->name }}</td>
                        <td class="px-3 py-3 text-sm text-neutral-800">{{ $order->seller?->name }}</td>
                        <td class="px-3 py-3 text-sm text-neutral-900 tabular">{{ $order->cryptoMoney()->format() }}</td>
                        <td class="px-3 py-3 text-sm text-neutral-600 tabular">{{ number_format((float) $order->fiat_amount, 2) }} {{ $order->fiat_currency }}</td>
                        <td class="px-3 py-3">
                            <x-ui.badge :color="$order->status->color()" dot>{{ $order->status->label() }}</x-ui.badge>
                            @php $risk = $order->meta['risk'] ?? null; @endphp
                            @if ($risk)
                                @php $rc = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger', 'critical' => 'danger'][$risk['level']] ?? 'muted'; @endphp
                                <x-ui.badge :color="$rc" title="{{ implode(', ', $risk['reasons'] ?? []) }}">⚠ {{ ucfirst($risk['level']) }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-sm text-neutral-500">{{ $order->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="user-group" :title="__('No orders')" :description="__('No P2P orders match your filters.')" /></td></tr>
                @endforelse
            </x-ui.table>
            <div class="mt-4">{{ $orders->links() }}</div>
        </x-ui.card>
    </div>
</x-layouts.admin>
