<x-layouts.admin :title="'Withdrawals'">
    <div class="space-y-6">
        <x-ui.page-header title="Withdrawals" subtitle="Approve or cancel outbound money. Reserve-before-sign — cancelling releases the ledger lock." />

        {{-- Status tabs (query-string filter) --}}
        <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
            @foreach ($tabs as $key => $count)
                <a href="{{ route('admin.withdrawals', ['status' => $key]) }}"
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

        <x-ui.table :headers="['User', 'Amount', 'Destination', 'Risk', 'Status', 'Requested', '']">
            @forelse ($withdrawals as $w)
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$w->user->name" size="sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-900">{{ $w->user->name }}</p>
                                <p class="truncate text-xs text-neutral-500">{{ $w->user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <x-ui.asset-icon :symbol="$w->asset->symbol" size="sm" />
                            <span class="tabular text-sm font-semibold text-neutral-900">{{ $w->money()->format() }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        @if ($w->payout_method)
                            <span class="text-xs capitalize text-neutral-600">{{ $w->payout_method }}</span>
                            @if (is_array($w->payout_details) && $w->payout_details)
                                <span class="block font-mono text-xs text-neutral-400">{{ \Illuminate\Support\Str::limit(implode(' · ', array_map('strval', $w->payout_details)), 24) }}</span>
                            @endif
                        @else
                            <span class="font-mono text-xs text-neutral-500">{{ \Illuminate\Support\Str::limit($w->to_address, 18) }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :color="$w->risk_level->color()">{{ $w->risk_level->label() }}</x-ui.badge>
                        <span class="ml-1 text-xs text-neutral-400">{{ $w->risk_score }}</span>
                    </td>
                    <td class="px-4 py-3"><x-ui.badge :color="$w->status->color()" dot>{{ $w->status->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $w->created_at->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        @if ($w->status->isReversibleLock() && $canApprove)
                            <div class="flex justify-end gap-2">
                                <form method="POST" action="{{ route('admin.withdrawals.approve', $w->id) }}"
                                    onsubmit="return confirm('Approve and queue for signing?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="success" size="sm" icon="check">Approve</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('admin.withdrawals.cancel', $w->id) }}"
                                    onsubmit="return confirm('Cancel and release the locked funds?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm" icon="x-mark">Cancel</x-ui.button>
                                </form>
                            </div>
                        @else
                            <span class="text-xs text-neutral-400">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="arrow-up-tray" title="No withdrawals" description="Nothing in this queue." /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $withdrawals->links() }}
    </div>
</x-layouts.admin>
