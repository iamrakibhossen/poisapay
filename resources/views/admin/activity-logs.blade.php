<x-layouts.admin :title="__('Activity Logs')">
    <div class="space-y-6" x-data="{ expanded: null }">
        <x-ui.page-header :title="__('Activity Logs')" :subtitle="__('Immutable audit trail of operator, user and system actions across the platform.')" />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            {{-- Actor-type tabs (query-string filter) --}}
            <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
                @foreach ($tabs as $key => $count)
                    <a href="{{ route('admin.activity-logs', array_filter(['actorType' => $key, 'search' => $search])) }}"
                        @class([
                            'flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium capitalize transition',
                            'bg-white text-neutral-900 shadow-sm' => $actorType === $key,
                            'text-neutral-500 hover:text-neutral-800' => $actorType !== $key,
                        ])>
                        {{ $key }}
                        <span class="rounded-full bg-neutral-200 px-1.5 text-xs">{{ $count }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.activity-logs') }}" class="w-full sm:w-72">
                <input type="hidden" name="actorType" value="{{ $actorType }}">
                <x-ui.input name="search" :value="$search" icon="magnifying-glass" :placeholder="__('Search action, description, actor…')" />
            </form>
        </div>

        <x-ui.table :headers="[__('When'), __('Actor'), __('Action'), __('Description'), __('IP'), '']">
            @forelse ($logs as $log)
                @php
                    $actorColor = match ($log->actor_type) {
                        'operator' => 'primary',
                        'user' => 'info',
                        default => 'gray',
                    };
                    $hasChanges = ! empty($log->changes);
                @endphp
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3 text-sm text-neutral-500">
                        <span title="{{ $log->created_at->toDayDateTimeString() }}">{{ $log->created_at->diffForHumans() }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-neutral-900">{{ $log->actor_name }}</span>
                            <x-ui.badge :color="$actorColor">{{ $log->actor_type }}</x-ui.badge>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs text-neutral-700">{{ $log->action }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $log->description ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-500"><span class="tabular">{{ $log->ip_address ?? '—' }}</span></td>
                    <td class="px-4 py-3 text-right">
                        @if ($hasChanges)
                            <button type="button" @click="expanded = (expanded === '{{ $log->id }}') ? null : '{{ $log->id }}'"
                                class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                                <span x-text="expanded === '{{ $log->id }}' ? 'Hide' : 'Details'"></span>
                                <x-heroicon-m-chevron-down class="h-3.5 w-3.5 transition" x-bind:class="expanded === '{{ $log->id }}' && 'rotate-180'" />
                            </button>
                        @endif
                    </td>
                </tr>
                @if ($hasChanges)
                    <tr x-show="expanded === '{{ $log->id }}'" x-cloak>
                        <td colspan="6" class="bg-neutral-50 px-4 py-3">
                            <pre class="overflow-x-auto rounded-lg border border-neutral-200 bg-white p-3 font-mono text-xs text-neutral-700">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="6"><x-ui.empty-state icon="clipboard-document-list" :title="__('No activity')" :description="__('No audit entries match this view yet.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $logs->links() }}
    </div>
</x-layouts.admin>
