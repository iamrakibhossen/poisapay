<x-layouts.admin :title="__('Financial Reports')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Financial Reports')" :subtitle="__('Trial balance, income statement, and reserve proof — recomputed live from the ledger.')">
            <x-slot:actions>
                @if ($canExport)
                    <x-ui.button :href="route('admin.reports.export')" variant="secondary" size="sm" icon="arrow-down-tray">{{ __('Export CSV') }}</x-ui.button>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        {{-- KPI strip --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card :label="__('Asset ledgers')" :value="(string) $assetCount" icon="banknotes" accent="brand" />
            <x-ui.stat-card
                :label="__('Book balance')"
                :value="$trialBalance['balanced'] ? __('Balanced') : __('Out of balance')"
                icon="scale"
                :accent="$trialBalance['balanced'] ? 'emerald' : 'rose'"
            />
            <x-ui.stat-card :label="__('Solvent assets')" :value="(string) $solventCount" icon="shield-check" accent="emerald" />
        </div>

        {{-- Trial balance --}}
        <x-ui.card :title="__('Trial balance')" :subtitle="__('Debit and credit totals per account type and asset. The book must balance.')">
            <x-slot:actions>
                @if ($trialBalance['balanced'])
                    <x-ui.badge color="success" dot>{{ __('Balanced ✓') }}</x-ui.badge>
                @else
                    <x-ui.badge color="danger" dot>{{ __('Out of balance ✗') }}</x-ui.badge>
                @endif
            </x-slot:actions>

            <x-ui.table :headers="[__('Account type'), __('Asset'), __('Debit'), __('Credit'), __('Balance')]">
                @forelse ($trialBalance['rows'] as $row)
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="px-3 py-3 text-sm font-medium text-neutral-900">{{ $row['type'] }}</td>
                        <td class="px-3 py-3 text-sm text-neutral-700">{{ $row['asset'] }}</td>
                        <td class="px-3 py-3 text-right"><span class="tabular text-sm text-neutral-700">{{ $row['debit'] }}</span></td>
                        <td class="px-3 py-3 text-right"><span class="tabular text-sm text-neutral-700">{{ $row['credit'] }}</span></td>
                        <td class="px-3 py-3 text-right"><span class="tabular text-sm font-semibold text-neutral-900">{{ $row['balance'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state icon="book-open" :title="__('No ledger activity')" :description="__('Nothing has been posted to the ledger yet.')" /></td></tr>
                @endforelse

                @if (! empty($trialBalance['rows']))
                    <x-slot:footer>
                        <div class="flex flex-col gap-1 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-neutral-500">{{ __('Grand totals (base units)') }}</span>
                            <span class="flex gap-6">
                                <span class="tabular text-neutral-700">{{ __('Debit:') }} <span class="font-semibold text-neutral-900">{{ $trialBalance['total_debit'] }}</span></span>
                                <span class="tabular text-neutral-700">{{ __('Credit:') }} <span class="font-semibold text-neutral-900">{{ $trialBalance['total_credit'] }}</span></span>
                            </span>
                        </div>
                    </x-slot:footer>
                @endif
            </x-ui.table>
        </x-ui.card>

        {{-- Income statement --}}
        <x-ui.card :title="__('Income statement')" :subtitle="__('Fee and spread income against gas and card program losses, per asset.')">
            @if (empty($incomeStatement))
                <x-ui.empty-state icon="chart-bar" :title="__('No income yet')" :description="__('Once fees or spread accrue, the income statement will populate here.')" />
            @else
                <x-ui.table :headers="[__('Asset'), __('Income'), __('Expense'), __('Net')]">
                    @foreach ($incomeStatement as $row)
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="px-3 py-3 text-sm font-medium text-neutral-900">{{ $row['asset'] }}</td>
                            <td class="px-3 py-3 text-right"><span class="tabular text-sm text-neutral-700">{{ $row['income'] }}</span></td>
                            <td class="px-3 py-3 text-right"><span class="tabular text-sm text-neutral-700">{{ $row['expense'] }}</span></td>
                            <td class="px-3 py-3 text-right"><span class="tabular text-sm font-semibold {{ $row['net_positive'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $row['net'] }}</span></td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>

        {{-- Solvency / reserve proof --}}
        <x-ui.card :title="__('Solvency (reserve proof)')" :subtitle="__('Treasury controlled must cover user liabilities, per asset.')">
            @if (empty($solvency))
                <x-ui.empty-state icon="shield-check" :title="__('No balances to prove')" :description="__('No treasury or user balances recorded yet.')" />
            @else
                <x-ui.table :headers="[__('Asset'), __('Treasury'), __('Liabilities'), __('Surplus'), __('Status')]">
                    @foreach ($solvency as $row)
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="px-3 py-3 text-sm font-medium text-neutral-900">{{ $row['asset'] }}</td>
                            <td class="px-3 py-3 text-right"><span class="tabular text-sm text-neutral-700">{{ $row['treasury'] }}</span></td>
                            <td class="px-3 py-3 text-right"><span class="tabular text-sm text-neutral-700">{{ $row['liabilities'] }}</span></td>
                            <td class="px-3 py-3 text-right"><span class="tabular text-sm font-semibold {{ $row['solvent'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $row['surplus'] }}</span></td>
                            <td class="px-3 py-3 text-right"><x-ui.badge :color="$row['solvent'] ? 'success' : 'danger'" dot>{{ $row['solvent'] ? __('Solvent') : __('Insolvent') }}</x-ui.badge></td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
