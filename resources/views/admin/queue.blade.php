<x-layouts.admin :title="__('Queue')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Queue')" :subtitle="__('Background job health — pending depth and failed jobs.')">
            <x-slot:actions>
                <x-ui.button :href="$horizonUrl" target="_blank" variant="secondary" size="sm" icon="arrow-top-right-on-square">{{ __('Open Horizon') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="grid grid-cols-2 gap-4">
            <x-ui.stat-card :label="__('Pending jobs')" :value="$pending" icon="queue-list" :accent="$pending > 0 ? 'amber' : 'emerald'" />
            <x-ui.stat-card :label="__('Failed jobs')" :value="$failedCount" icon="exclamation-triangle" :accent="$failedCount > 0 ? 'rose' : 'emerald'" />
        </div>

        <div>
            <h3 class="mb-2 text-sm font-semibold text-neutral-700">{{ __('Failed jobs') }}</h3>
            <x-ui.table :headers="[__('Queue'), __('Connection'), __('Error'), __('Failed')]">
                @forelse ($failed as $job)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3 text-sm font-medium text-neutral-900">{{ $job->queue }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $job->connection }}</td>
                        <td class="px-4 py-3"><span class="font-mono text-xs text-neutral-500">{{ Str::limit(strtok($job->exception, "\n"), 80) }}</span></td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ \Illuminate\Support\Carbon::parse($job->failed_at)->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-ui.empty-state icon="check-circle" :title="__('No failed jobs')" :description="__('The queue is healthy.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-layouts.admin>
