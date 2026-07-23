<x-layouts.admin :title="__('Revenue Withdrawals')">
    <div class="space-y-6"
        x-data="{
            showApprove: {{ $errors->any() ? 'true' : 'false' }},
            approveId: @js(old('_approve_id', '')),
            open(id) { this.approveId = id; this.showApprove = true; },
            close() { this.showApprove = false; this.approveId = null; },
        }">
        <x-ui.page-header :title="__('Revenue Withdrawals')" :subtitle="__('Approve outbound revenue payouts. Approval posts the ledger and queues the on-chain broadcast.')" />

        {{-- Stat tiles --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <x-ui.stat-card :label="__('Pending')" :value="number_format($pendingCount)" icon="clock" accent="amber" />
            <x-ui.stat-card :label="__('Completed')" :value="number_format($completedCount)" icon="check-circle" accent="emerald" />
            <x-ui.stat-card :label="__('Total Withdrawn')" :value="$totalWithdrawn" icon="banknotes" accent="brand" />
        </div>

        {{-- Status tabs (query-string filter) --}}
        <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
            @foreach ($tabs as $key => $count)
                <a href="{{ route('admin.revenue-withdrawals', ['status' => $key]) }}"
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

        <x-ui.table :headers="[__('Amount'), __('Asset'), __('Network'), __('Destination'), __('Status'), __('Requested by'), __('Approved by'), __('Tx hash'), __('Created'), '']">
            @forelse ($withdrawals as $w)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-3 py-3">
                        <span class="tabular text-sm font-semibold text-neutral-900">{{ $w->money()->format() }}</span>
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-2">
                            <x-ui.asset-icon :symbol="$w->asset?->symbol" size="sm" />
                            <span class="text-sm text-neutral-600">{{ $w->asset?->symbol }}</span>
                        </div>
                    </td>
                    <td class="px-3 py-3 text-sm text-neutral-600">{{ $w->network ?: '—' }}</td>
                    <td class="px-3 py-3"><span class="font-mono text-xs text-neutral-500">{{ \Illuminate\Support\Str::limit($w->destination_address, 18) }}</span></td>
                    <td class="px-3 py-3">
                        <x-ui.badge :color="$w->status->color()" dot>{{ ucfirst($w->status->value) }}</x-ui.badge>
                        @if ($w->status === \App\Enums\RevenueWithdrawalStatus::Failed && $w->failure_reason)
                            <x-ui.tooltip :message="$w->failure_reason" width="w-56">
                                <x-heroicon-o-exclamation-circle class="ml-1 inline-block h-4 w-4 text-red-500" />
                            </x-ui.tooltip>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-sm text-neutral-500">{{ $w->creator?->name ?? '—' }}</td>
                    <td class="px-3 py-3 text-sm text-neutral-500">{{ $w->approver?->name ?? '—' }}</td>
                    <td class="px-3 py-3">
                        @if ($w->tx_hash)
                            <span class="font-mono text-xs text-neutral-500">{{ \Illuminate\Support\Str::limit($w->tx_hash, 14) }}</span>
                        @else
                            <span class="text-xs text-neutral-400">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-xs text-neutral-500">{{ $w->created_at?->diffForHumans() }}</td>
                    <td class="px-3 py-3 text-right">
                        @if ($canApprove && $w->status === \App\Enums\RevenueWithdrawalStatus::Pending)
                            <x-ui.button type="button" x-on:click="open({{ \Illuminate\Support\Js::from((string) $w->id) }})" variant="success" size="sm" icon="check">{{ __('Approve') }}</x-ui.button>
                        @else
                            <span class="text-xs text-neutral-400">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10"><x-ui.empty-state icon="banknotes" :title="__('No withdrawals')" :description="__('Nothing in this queue.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $withdrawals->links() }}

        {{-- Approve modal --}}
        @if ($canApprove)
            <div x-show="showApprove" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="close()"></div>
                <div class="relative w-full max-w-md pp-card p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">{{ __('Approve revenue withdrawal') }}</h3>
                        <button type="button" x-on:click="close()" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <p class="mb-4 text-sm text-neutral-500">{{ __('Approving posts the ledger entry and queues the on-chain broadcast. This cannot be undone.') }}</p>
                    <form method="POST" x-bind:action="'{{ url('admin/finance/revenue-withdrawals') }}/' + approveId + '/approve'" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_approve_id" x-bind:value="approveId">
                        <x-ui.input :label="__('Confirm your password')" name="password" type="password" placeholder="••••••••" :error="$errors->first('password')" />
                        <div class="flex justify-end gap-2 pt-1">
                            <x-ui.button type="button" variant="secondary" x-on:click="close()">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="submit" variant="success" icon="check">{{ __('Approve & broadcast') }}</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.admin>
