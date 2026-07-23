<x-layouts.admin :title="__('Webhook Logs')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Webhook Logs')" :subtitle="__('Every inbound webhook request our endpoints received — payload, headers and our response, for audit and debugging.')" />

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card :label="__('Total logged')" :value="number_format($stats['total'])" icon="inbox-stack" accent="brand" />
            <x-ui.stat-card :label="__('Unresolved')" :value="number_format($stats['unresolved'])" icon="clock" accent="amber" />
            <x-ui.stat-card :label="__('Failed (4xx/5xx)')" :value="number_format($stats['failed'])" icon="x-circle" accent="rose" />
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.webhook-logs') }}" class="flex flex-col gap-3 sm:flex-row">
            <x-ui.select name="provider" class="w-auto" onchange="this.form.submit()">
                <option value="all" @selected($provider === 'all')>{{ __('All providers') }}</option>
                @foreach ($providers as $p)
                    <option value="{{ $p }}" @selected($provider === $p)>{{ ucfirst($p) }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select name="state" class="w-auto" onchange="this.form.submit()">
                @foreach (['all' => __('All'), 'unresolved' => __('Unresolved'), 'resolved' => __('Resolved'), 'failed' => __('Failed')] as $k => $label)
                    <option value="{{ $k }}" @selected($state === $k)>{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.input name="search" :value="$search" placeholder="{{ __('Search URL…') }}" class="sm:w-72" />
            <x-ui.button type="submit" variant="secondary" icon="magnifying-glass">{{ __('Search') }}</x-ui.button>
        </form>

        <x-ui.table :headers="[__('Provider'), __('Route'), __('Method'), __('Status'), __('Resolved'), __('When'), '']">
            @forelse ($logs as $log)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-4 py-3"><x-ui.badge color="gray">{{ $log->provider ?: '—' }}</x-ui.badge></td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $log->route ?: \Illuminate\Support\Str::limit($log->url, 40) }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $log->method }}</td>
                    <td class="px-4 py-3">
                        <x-ui.badge :color="$log->status >= 500 ? 'danger' : ($log->status >= 400 ? 'warning' : 'success')">{{ $log->status ?: '—' }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-3"><x-ui.badge :color="$log->resolved ? 'success' : 'gray'" dot>{{ $log->resolved ? __('Yes') : __('No') }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-xs text-gray-400">{{ $log->created_at?->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        <x-ui.button :href="route('admin.webhook-logs.show', $log->id)" variant="secondary" size="sm" icon="eye">{{ __('View') }}</x-ui.button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="inbox-stack" :title="__('No webhook logs')" :description="__('No inbound webhook requests have been recorded yet.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $logs->links() }}
    </div>
</x-layouts.admin>
