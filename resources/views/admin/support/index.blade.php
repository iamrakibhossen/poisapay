<x-layouts.admin :title="'Support'">
    <div class="space-y-6">
        <x-ui.page-header title="Support Tickets" subtitle="Triage, reply to, and resolve user tickets." />

        {{-- Stat cards --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card label="Open" :value="number_format($stats['open'])" icon="inbox-arrow-down" accent="brand" />
            <x-ui.stat-card label="Awaiting user" :value="number_format($stats['pending'])" icon="clock" accent="amber" />
        </div>

        {{-- Filter tabs (query-string filter) --}}
        @php
            $filters = ['active' => 'Active', 'open' => 'Open', 'pending' => 'Pending', 'resolved' => 'Resolved', 'closed' => 'Closed', 'all' => 'All'];
        @endphp
        <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
            @foreach ($filters as $key => $label)
                <a href="{{ route('admin.support', ['status' => $key]) }}"
                    @class([
                        'flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition',
                        'bg-white text-neutral-900 shadow-sm' => $status === $key,
                        'text-neutral-500 hover:text-neutral-800' => $status !== $key,
                    ])>
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <x-ui.table :headers="['User', 'Subject', 'Category', 'Status', 'Assigned', 'Updated', '']">
            @forelse ($tickets as $ticket)
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$ticket->user?->name ?? '—'" size="sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-900">{{ $ticket->user?->name ?? '—' }}</p>
                                <p class="truncate text-xs text-neutral-500">{{ $ticket->user?->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.support.show', $ticket->id) }}" class="text-sm font-medium text-brand-600 hover:underline">{{ $ticket->subject }}</a>
                        <p class="text-xs text-neutral-400">{{ $ticket->messages_count }} message{{ $ticket->messages_count === 1 ? '' : 's' }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ ucfirst($ticket->category) }}</td>
                    <td class="px-4 py-3"><x-ui.badge :color="$ticket->status->color()" dot>{{ $ticket->status->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $ticket->assignedTo?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $ticket->updated_at->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.support.show', $ticket->id) }}"><x-ui.button variant="secondary" size="sm">Open</x-ui.button></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="lifebuoy" title="No tickets" description="Nothing matches this filter." /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $tickets->links() }}
    </div>
</x-layouts.admin>
