<x-layouts.admin :title="__('Card Provider Logs')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Card Provider Logs')" :subtitle="__('Every provider API call — outbound requests and inbound webhooks/JIT. Secrets are redacted.')" />

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card :label="__('Total calls')" :value="number_format($stats['total'])" icon="signal" accent="brand" />
            <x-ui.stat-card :label="__('Failures (24h)')" :value="number_format($stats['failures'])" icon="exclamation-triangle" accent="amber" />
            <x-ui.stat-card :label="__('Calls today')" :value="number_format($stats['today'])" icon="clock" accent="emerald" />
        </div>

        <form method="GET" action="{{ route('admin.card-logs') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                <x-ui.select name="driver" class="w-auto" onchange="this.form.submit()">
                    <option value="all" @selected($driver === 'all')>{{ __('All providers') }}</option>
                    @foreach ($drivers as $d)
                        <option value="{{ $d }}" @selected($driver === $d)>{{ ucfirst($d) }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="direction" class="w-auto" onchange="this.form.submit()">
                    <option value="all" @selected($direction === 'all')>{{ __('All directions') }}</option>
                    <option value="outbound" @selected($direction === 'outbound')>{{ __('Outbound') }}</option>
                    <option value="inbound" @selected($direction === 'inbound')>{{ __('Inbound') }}</option>
                </x-ui.select>
                <x-ui.select name="result" class="w-auto" onchange="this.form.submit()">
                    <option value="all" @selected($result === 'all')>{{ __('All results') }}</option>
                    <option value="ok" @selected($result === 'ok')>{{ __('Success') }}</option>
                    <option value="error" @selected($result === 'error')>{{ __('Error') }}</option>
                </x-ui.select>
            </div>
            <x-ui.input name="search" :value="$search" icon="magnifying-glass" :placeholder="__('Search operation, endpoint or error…')" class="w-full sm:w-72" />
        </form>

        <x-ui.table :headers="[__('Time'), __('Provider'), __('Dir'), __('Operation'), __('Endpoint'), __('Code'), __('Latency'), __('Result')]">
            @forelse ($logs as $log)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-3 py-3 text-xs text-neutral-500">{{ $log->created_at?->format('M j, H:i:s') }}</td>
                    <td class="px-3 py-3 text-sm font-medium text-neutral-800">{{ ucfirst($log->driver) }}</td>
                    <td class="px-3 py-3"><x-ui.badge :color="$log->direction === 'outbound' ? 'info' : 'gray'">{{ $log->direction }}</x-ui.badge></td>
                    <td class="px-3 py-3 text-sm text-neutral-700">{{ $log->operation }}</td>
                    <td class="px-3 py-3 font-mono text-xs text-neutral-400">{{ \Illuminate\Support\Str::limit($log->endpoint, 40) ?: '—' }}</td>
                    <td class="px-3 py-3 text-sm tabular text-neutral-600">{{ $log->status_code ?? '—' }}</td>
                    <td class="px-3 py-3 text-sm tabular text-neutral-500">{{ $log->latency_ms !== null ? $log->latency_ms.'ms' : '—' }}</td>
                    <td class="px-3 py-3">
                        <x-ui.badge :color="$log->success ? 'success' : 'danger'">{{ $log->success ? __('OK') : __('Error') }}</x-ui.badge>
                        @if (! $log->success && $log->error)
                            <p class="mt-1 max-w-xs truncate text-xs text-rose-500" title="{{ $log->error }}">{{ $log->error }}</p>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-ui.empty-state icon="signal" :title="__('No calls')" :description="__('No provider calls match your filters.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $logs->links() }}
    </div>
</x-layouts.admin>
