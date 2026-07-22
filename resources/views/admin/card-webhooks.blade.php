<x-layouts.admin :title="'Card Webhooks'">
    <div class="space-y-6">
        <x-ui.page-header title="Card Webhooks" subtitle="Inbound provider events — verified, deduplicated and processed off-request. Retry any that failed." />

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card label="Pending" :value="number_format($stats['pending'])" icon="clock" accent="amber" />
            <x-ui.stat-card label="Failed" :value="number_format($stats['failed'])" icon="exclamation-triangle" accent="brand" />
            <x-ui.stat-card label="Processed today" :value="number_format($stats['processed'])" icon="check-circle" accent="emerald" />
        </div>

        <form method="GET" action="{{ route('admin.card-webhooks') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                <x-ui.select name="driver" class="w-auto" onchange="this.form.submit()">
                    <option value="all" @selected($driver === 'all')>All providers</option>
                    @foreach ($drivers as $d)
                        <option value="{{ $d }}" @selected($driver === $d)>{{ ucfirst($d) }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="status" class="w-auto" onchange="this.form.submit()">
                    <option value="all" @selected($status === 'all')>All statuses</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="processed" @selected($status === 'processed')>Processed</option>
                    <option value="ignored" @selected($status === 'ignored')>Ignored</option>
                    <option value="failed" @selected($status === 'failed')>Failed</option>
                </x-ui.select>
            </div>
            <x-ui.input name="search" :value="$search" icon="magnifying-glass" placeholder="Search event, id or tx ref…" class="w-full sm:w-72" />
        </form>

        <x-ui.table :headers="['Received', 'Provider', 'Event', 'Event ID', 'Tx Ref', 'Sig', 'Tries', 'Status', '']">
            @forelse ($webhooks as $webhook)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-3 py-3 text-xs text-neutral-500">{{ ($webhook->received_at ?? $webhook->created_at)?->format('M j, H:i:s') }}</td>
                    <td class="px-3 py-3 text-sm font-medium text-neutral-800">{{ ucfirst($webhook->driver) }}</td>
                    <td class="px-3 py-3 text-sm text-neutral-700">{{ $webhook->event_type }}</td>
                    <td class="px-3 py-3 font-mono text-xs text-neutral-400">{{ \Illuminate\Support\Str::limit($webhook->provider_event_id, 22) }}</td>
                    <td class="px-3 py-3 font-mono text-xs text-neutral-400">{{ $webhook->provider_tx_ref ? \Illuminate\Support\Str::limit($webhook->provider_tx_ref, 18) : '—' }}</td>
                    <td class="px-3 py-3"><x-ui.badge :color="$webhook->signature_valid ? 'success' : 'danger'">{{ $webhook->signature_valid ? '✓' : '✕' }}</x-ui.badge></td>
                    <td class="px-3 py-3 text-sm tabular text-neutral-500">{{ $webhook->attempts }}</td>
                    <td class="px-3 py-3">
                        @php $color = ['processed' => 'success', 'failed' => 'danger', 'ignored' => 'gray', 'pending' => 'warning'][$webhook->status] ?? 'gray'; @endphp
                        <x-ui.badge :color="$color" dot>{{ ucfirst($webhook->status) }}</x-ui.badge>
                        @if ($webhook->status === 'failed' && $webhook->error)
                            <p class="mt-1 max-w-xs truncate text-xs text-rose-500" title="{{ $webhook->error }}">{{ $webhook->error }}</p>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-right">
                        @if ($canManage && in_array($webhook->status, ['failed', 'pending'], true))
                            <form method="POST" action="{{ route('admin.card-webhooks.retry', $webhook->id) }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" size="sm" icon="arrow-path">Retry</x-ui.button>
                            </form>
                        @else
                            <span class="text-xs text-neutral-300">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><x-ui.empty-state icon="inbox" title="No webhooks" description="No provider events match your filters." /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $webhooks->links() }}
    </div>
</x-layouts.admin>
