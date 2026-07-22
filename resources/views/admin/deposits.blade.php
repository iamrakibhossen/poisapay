<x-layouts.admin :title="'Deposits'">
    <div class="space-y-6">
        <x-ui.page-header title="Deposits" subtitle="Inbound settlements — on-chain and manual, detected, confirming and credited." />

        <form method="GET" action="{{ route('admin.deposits') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            {{-- Status tabs (query-string filter) --}}
            <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
                @foreach ($tabs as $key => $count)
                    <a href="{{ route('admin.deposits', array_filter(['status' => $key, 'search' => $search])) }}"
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

            <input type="hidden" name="status" value="{{ $status }}" />
            <x-ui.input name="search" :value="$search" icon="magnifying-glass" placeholder="Search user email…" class="w-full sm:w-64" />
        </form>

        <x-ui.table :headers="['User', 'Asset', 'Amount', 'Source', 'Status', 'Progress', 'Created', '']">
            @forelse ($deposits as $deposit)
                @php($isManual = $deposit->source === 'manual')
                @php($canAct = $isManual && $deposit->status === \App\Enums\DepositStatus::Detected)
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$deposit->user->name" size="sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-900">{{ $deposit->user->name }}</p>
                                <p class="truncate text-xs text-neutral-500">{{ $deposit->user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <x-ui.asset-icon :symbol="$deposit->asset->symbol" size="sm" />
                            <span class="text-sm font-medium text-neutral-900">{{ $deposit->asset->symbol }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3"><span class="tabular text-sm font-semibold text-neutral-900">{{ $deposit->money()->format() }}</span></td>
                    <td class="px-4 py-3">
                        @if ($isManual)
                            <x-ui.badge color="info">Manual</x-ui.badge>
                            <p class="mt-1 text-xs text-neutral-500">{{ $deposit->depositMethod?->name ?? '—' }}</p>
                            @if ($deposit->reference)
                                <p class="font-mono text-xs text-neutral-400">{{ $deposit->reference }}</p>
                            @endif
                        @else
                            <x-ui.badge color="gray">On-chain</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-3"><x-ui.badge :color="$deposit->status->color()" dot>{{ $deposit->status->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-600">
                        @if ($isManual)
                            <span class="text-neutral-400">—</span>
                        @else
                            <span class="tabular">{{ $deposit->confirmations }} / {{ $deposit->required_confirmations }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $deposit->created_at->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        @if ($canAct)
                            <div class="flex justify-end gap-2">
                                <form method="POST" action="{{ route('admin.deposits.approve', $deposit->id) }}"
                                    onsubmit="return confirm('Approve and credit this deposit?')">
                                    @csrf
                                    <x-ui.button type="submit" size="sm" icon="check">Approve</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('admin.deposits.reject', $deposit->id) }}"
                                    onsubmit="return confirm('Reject this deposit?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm" icon="x-mark">Reject</x-ui.button>
                                </form>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-ui.empty-state icon="arrow-down-tray" title="No deposits" description="Nothing in this queue." /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $deposits->links() }}
    </div>
</x-layouts.admin>
