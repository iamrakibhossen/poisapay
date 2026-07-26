<x-layouts.app :title="__('Orders')">
    <div class="mt-6 space-y-5">
        <div>
            <a href="{{ route('shop') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900"><x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Shop') }}</a>
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Orders') }}</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ __('Every sale across your products.') }}</p>
        </div>

        <x-ui.history-stats :cards="[
            ['label' => __('All-time'), 'value' => number_format($stats['total']), 'icon' => 'inbox-stack'],
            ['label' => __('Revenue'), 'value' => $stats['revenue'], 'icon' => 'banknotes', 'fg' => 'text-emerald-600'],
            ['label' => __('Processing'), 'value' => number_format($stats['pending']), 'icon' => 'clock', 'fg' => 'text-amber-600'],
            ['label' => __('Refunded'), 'value' => number_format($stats['refunded']), 'icon' => 'arrow-uturn-left'],
        ]" />

        @if (count($orders))
        <x-ui.history-table :columns="[
            ['label' => __('Order')],
            ['label' => __('Customer')],
            ['label' => __('Product')],
            ['label' => __('Status')],
            ['label' => __('Amount'), 'align' => 'right'],
            ['label' => __('Date'), 'align' => 'right'],
        ]">
            @foreach ($orders as $o)
                <tr class="cursor-pointer transition hover:bg-neutral-50/70" onclick="window.location='{{ route('shop.order', ['id' => $o['id']]) }}'">
                    <td class="px-5 py-4 align-middle font-mono text-xs font-medium text-brand-600">{{ $o['number'] }}</td>
                    <td class="px-5 py-4 align-middle text-sm text-neutral-700">{{ $o['buyer'] }}</td>
                    <td class="px-5 py-4 align-middle text-sm font-medium text-neutral-900">{{ $o['product'] }}</td>
                    <td class="px-5 py-4 align-middle"><x-ui.badge :color="$o['color']" dot>{{ $o['status'] }}</x-ui.badge></td>
                    <td class="tabular px-5 py-4 text-right align-middle text-sm font-semibold text-neutral-900">{{ $o['amount'] }}</td>
                    <td class="whitespace-nowrap px-5 py-4 text-right align-middle text-xs text-neutral-400">{{ $o['date'] }}</td>
                </tr>
            @endforeach
        </x-ui.history-table>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="inbox-stack" :title="__('No orders yet')" :description="__('When a buyer purchases one of your products, it shows up here.')" />
            </div>
        @endif
    </div>
</x-layouts.app>
