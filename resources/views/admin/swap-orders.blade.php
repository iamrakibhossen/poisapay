<x-layouts.admin :title="__('Swap Orders')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Swap Orders')" :subtitle="__('Executed conversions — swaps, ramp settlements and card settlements.')" />

        <form method="GET" action="{{ route('admin.swap-orders') }}">
            <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
                @foreach ($tabs as $key => $count)
                    <a href="{{ route('admin.swap-orders', ['context' => $key]) }}"
                        @class([
                            'flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium capitalize transition',
                            'bg-white text-neutral-900 shadow-sm' => $context === $key,
                            'text-neutral-500 hover:text-neutral-800' => $context !== $key,
                        ])>
                        {{ str_replace('_', ' ', $key) }}
                        <span class="rounded-full bg-neutral-200 px-1.5 text-xs">{{ $count }}</span>
                    </a>
                @endforeach
            </div>
        </form>

        <x-ui.table :headers="[__('User'), __('From'), __('To'), __('Rate'), __('Context'), __('When')]">
            @forelse ($orders as $order)
                @php($quote = $order->quote)
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3">
                        <p class="truncate text-sm font-medium text-neutral-900">{{ $order->user?->name ?? '—' }}</p>
                        <p class="truncate text-xs text-neutral-500">{{ $order->user?->email }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-900">
                        @if ($quote?->fromAsset)
                            <span class="tabular font-medium">{{ $quote->fromAsset->money($quote->from_amount)->format() }}</span>
                        @else — @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-900">
                        @if ($quote?->toAsset)
                            <span class="tabular font-medium">{{ $quote->toAsset->money($quote->to_amount)->format() }}</span>
                        @else — @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-500 tabular">{{ $quote ? rtrim(rtrim(number_format((float) $quote->rate, 8, '.', ''), '0'), '.') : '—' }}</td>
                    <td class="px-4 py-3"><x-ui.badge :color="$order->context->color()">{{ $order->context->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $order->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state icon="arrow-path-rounded-square" :title="__('No swap orders')" :description="__('Conversions appear here as users swap or settle.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $orders->links() }}
    </div>
</x-layouts.admin>
