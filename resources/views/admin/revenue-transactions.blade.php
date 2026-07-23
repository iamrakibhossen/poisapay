<x-layouts.admin :title="__('Revenue Transactions')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Revenue Transactions')" :subtitle="__('Every fee credit that makes up the revenue wallet — searchable and exportable.')">
            <x-slot:actions>
                <x-ui.button :href="route('admin.revenue-transactions.export', request()->query())" variant="secondary" icon="arrow-down-tray">{{ __('Export CSV') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Filters (query-string GET form) --}}
        <form method="GET" action="{{ route('admin.revenue-transactions') }}" class="pp-card p-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <x-ui.input :label="__('From')" name="from" type="date" :value="$from" />
                <x-ui.input :label="__('To')" name="to" type="date" :value="$to" />
                <x-ui.input :label="__('User')" name="user" :placeholder="__('Name or email')" icon="magnifying-glass" :value="$user" />
                <x-ui.select :label="__('Fee type')" name="feeType">
                    <option value="">{{ __('All fee types') }}</option>
                    @foreach ($feeTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected($feeType === $value)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
                <div class="flex items-end">
                    <x-ui.button type="submit" icon="magnifying-glass">{{ __('Filter') }}</x-ui.button>
                </div>
                <div class="flex items-end">
                    <x-ui.button :href="route('admin.revenue-transactions')" variant="ghost" icon="x-mark">{{ __('Reset') }}</x-ui.button>
                </div>
            </div>
        </form>

        @php $revenue = app(\App\Domain\Revenue\RevenueService::class); @endphp

        <x-ui.table :headers="[__('Transaction ID'), __('User'), __('Fee Type'), __('Source'), __('Amount'), __('Currency'), __('Created At')]">
            @forelse ($rows as $row)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-3 py-3"><span class="font-mono text-xs text-neutral-500">{{ \Illuminate\Support\Str::limit((string) $row->entry_id, 12, '…') }}</span></td>
                    <td class="px-3 py-3">
                        @if ($row->user_name)
                            <p class="text-sm font-medium text-neutral-900">{{ $row->user_name }}</p>
                            <p class="text-xs text-neutral-500">{{ $row->user_email }}</p>
                        @else
                            <span class="text-xs text-neutral-400">{{ __('— / system') }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-3">
                        <x-ui.badge color="info">{{ $revenue->feeTypeLabel($row->account_type, $row->entry_type) }}</x-ui.badge>
                    </td>
                    <td class="px-3 py-3 text-sm text-neutral-600">{{ \Illuminate\Support\Str::headline((string) $row->entry_type) }}</td>
                    <td class="px-3 py-3">
                        <span class="tabular text-sm font-semibold text-emerald-600">{{ \App\Support\Money::ofBase($row->amount, $row->decimals, $row->symbol)->format() }}</span>
                    </td>
                    <td class="px-3 py-3 text-sm text-neutral-600">{{ $row->symbol }}</td>
                    <td class="px-3 py-3 text-xs text-neutral-500">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="banknotes" :title="__('No revenue transactions')" :description="__('Fee credits matching your filters will appear here.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $rows->links() }}
    </div>
</x-layouts.admin>
