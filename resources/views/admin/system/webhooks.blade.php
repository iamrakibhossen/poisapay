<x-layouts.admin :title="__('Webhooks')">
    @php
        $deliveryColor = ['delivered' => 'success', 'pending' => 'warning', 'failed' => 'danger'];
    @endphp

    <div class="space-y-6">
        <x-ui.page-header :title="__('Outbound Webhooks')" :subtitle="__('Merchant endpoints and their delivery health. Replay failed deliveries after fixing the receiver.')" />

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif

        {{-- Stats --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card :label="__('Endpoints')" :value="number_format($stats['endpoints'])" icon="bolt" accent="brand" />
            <x-ui.stat-card :label="__('Active')" :value="number_format($stats['active'])" icon="check-circle" accent="emerald" />
            <x-ui.stat-card :label="__('Pending')" :value="number_format($stats['pending'])" icon="clock" accent="amber" />
            <x-ui.stat-card :label="__('Failed')" :value="number_format($stats['failed'])" icon="x-circle" accent="rose" />
        </div>

        {{-- Endpoints --}}
        <x-ui.card :title="__('Endpoints')" class="p-0">
            <x-ui.table :headers="[__('Owner'), __('URL'), __('Events'), __('Deliveries'), __('Status'), '']">
                @forelse ($endpoints as $ep)
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900">{{ $ep->user?->name ?? '—' }}</p>
                            <p class="text-xs text-gray-400">{{ $ep->user?->email }}</p>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ \Illuminate\Support\Str::limit($ep->url, 44) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ count($ep->events ?? []) }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            <span class="text-emerald-600">{{ $ep->delivered_count }} ✓</span>
                            @if ($ep->failed_count)<span class="ml-1 text-red-600">{{ $ep->failed_count }} ✕</span>@endif
                            <span class="text-gray-400">/ {{ $ep->deliveries_count }}</span>
                        </td>
                        <td class="px-4 py-3"><x-ui.badge :color="$ep->is_active ? 'success' : 'gray'" dot>{{ $ep->is_active ? __('Active') : __('Off') }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.webhooks.toggle', $ep->id) }}">
                                @csrf
                                <x-ui.button type="submit" size="sm" variant="secondary">{{ $ep->is_active ? __('Disable') : __('Enable') }}</x-ui.button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="bolt" :title="__('No endpoints')" :description="__('Merchants have not registered any webhook endpoints yet.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>

        {{-- Recent deliveries --}}
        <div>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900">{{ __('Recent deliveries') }}</h2>
                <div class="-mx-1 flex gap-1.5 px-1">
                    @foreach (['all', 'delivered', 'pending', 'failed'] as $s)
                        <a href="{{ route('admin.webhooks', ['status' => $s]) }}"
                            @class([
                                'rounded-full px-3 py-1 text-xs font-medium transition',
                                'bg-gray-900 text-white' => $status === $s,
                                'text-gray-500 hover:bg-gray-100' => $status !== $s,
                            ])>{{ ucfirst($s) }}</a>
                    @endforeach
                </div>
            </div>

            <x-ui.table :headers="[__('Event'), __('Owner'), __('Attempt'), __('HTTP'), __('Status'), __('When'), '']">
                @forelse ($deliveries as $d)
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $d->event }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $d->endpoint?->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 tabular">{{ $d->attempt }}</td>
                        <td class="px-4 py-3 tabular text-gray-600">{{ $d->response_status ?? '—' }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$deliveryColor[$d->status] ?? 'gray'" dot>{{ ucfirst($d->status) }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $d->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($d->status !== 'delivered')
                                <form method="POST" action="{{ route('admin.webhooks.retry', $d->id) }}">
                                    @csrf
                                    <x-ui.button type="submit" size="sm" variant="secondary" icon="arrow-path">{{ __('Retry') }}</x-ui.button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="inbox" :title="__('No deliveries')" :description="__('No webhook deliveries match this filter.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-layouts.admin>
