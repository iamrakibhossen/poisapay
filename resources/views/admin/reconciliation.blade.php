<x-layouts.admin :title="__('Reconciliation')">
    @php
        $runColor = ['ok' => 'success', 'drift' => 'warning', 'insolvent' => 'danger'];
    @endphp
    <div class="space-y-6">
        <x-ui.page-header :title="__('Reconciliation')" :subtitle="__('Ledger treasury vs. user liability over time — drift and solvency audit trail.')">
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.treasury.reconcile') }}"
                    onsubmit="return confirm('{{ __('Run reconciliation across all assets now?') }}')">
                    @csrf
                    <x-ui.button type="submit" size="sm" icon="arrow-path">{{ __('Run reconciliation') }}</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.page-header>

        <form method="GET" action="{{ route('admin.reconciliation') }}">
            <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
                @foreach ($tabs as $key => $count)
                    <a href="{{ route('admin.reconciliation', ['status' => $key]) }}"
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

        <x-ui.table :headers="[__('Asset'), __('Ledger Treasury'), __('User Liability'), __('Drift'), __('Solvent'), __('Status'), __('When')]">
            @forelse ($runs as $run)
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3 text-sm font-medium text-neutral-900">{{ $run->asset?->symbol ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-700 tabular">{{ $run->ledger_treasury }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-700 tabular">{{ $run->ledger_liability }}</td>
                    <td class="px-4 py-3 text-sm tabular {{ (string) $run->drift === '0' ? 'text-neutral-500' : 'text-amber-600 font-semibold' }}">{{ $run->drift }}</td>
                    <td class="px-4 py-3">
                        @if ($run->is_solvent)
                            <x-ui.badge color="success" dot>{{ __('Solvent') }}</x-ui.badge>
                        @else
                            <x-ui.badge color="danger" dot>{{ __('Insolvent') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-3"><x-ui.badge :color="$runColor[$run->status] ?? 'gray'">{{ ucfirst($run->status) }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $run->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="scale" :title="__('No reconciliation runs')" :description="__('Run a reconciliation to populate the audit trail.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $runs->links() }}
    </div>
</x-layouts.admin>
