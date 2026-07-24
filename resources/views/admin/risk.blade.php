<x-layouts.admin :title="__('Risk')">
    @php
        $sevColor = ['critical' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    @endphp
    <div class="space-y-6">
        <x-ui.page-header :title="__('Risk')" :subtitle="__('Elevated-risk withdrawals and recent security signals.')" />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-ui.stat-card :label="__('Critical withdrawals')" :value="$stats['critical']" icon="exclamation-triangle" accent="rose" />
            <x-ui.stat-card :label="__('High')" :value="$stats['high']" icon="exclamation-circle" accent="amber" />
            <x-ui.stat-card :label="__('Medium')" :value="$stats['medium']" icon="flag" accent="brand" />
            <x-ui.stat-card :label="__('Security events (24h)')" :value="$stats['events24h']" icon="shield-exclamation" accent="brand" />
        </div>

        <div>
            <h3 class="mb-2 text-sm font-semibold text-neutral-700">{{ __('Elevated-risk withdrawals') }}</h3>
            <x-ui.table :headers="[__('User'), __('Asset'), __('Amount'), __('Score'), __('Level'), __('Status'), __('When')]">
                @forelse ($withdrawals as $w)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3">
                            <p class="truncate text-sm font-medium text-neutral-900">{{ $w->user?->name ?? '—' }}</p>
                            <p class="truncate text-xs text-neutral-500">{{ $w->user?->email }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-700">{{ $w->asset?->symbol ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="tabular text-sm font-semibold text-neutral-900">{{ $w->asset ? $w->asset->money($w->amount)->format() : $w->amount }}</span></td>
                        <td class="px-4 py-3 text-sm text-neutral-600 tabular">{{ $w->risk_score }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$w->risk_level->color()" dot>{{ $w->risk_level->label() }}</x-ui.badge></td>
                        <td class="px-4 py-3"><x-ui.badge :color="$w->status->color()">{{ $w->status->label() }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $w->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="shield-check" :title="__('No elevated-risk withdrawals')" :description="__('Low-risk withdrawals auto-approve and do not appear here.')" /></td></tr>
                @endforelse
            </x-ui.table>
            {{ $withdrawals->links() }}
        </div>

        <div>
            <h3 class="mb-2 text-sm font-semibold text-neutral-700">{{ __('Recent security events') }}</h3>
            <x-ui.table :headers="[__('User'), __('Type'), __('Severity'), __('Score'), __('IP / Country'), __('When')]">
                @forelse ($events as $e)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3 text-sm text-neutral-700">{{ $e->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-700">{{ str_replace('_', ' ', $e->type) }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$sevColor[$e->severity] ?? 'gray'" dot>{{ ucfirst($e->severity) }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-sm text-neutral-600 tabular">{{ $e->risk_score }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $e->ip_address }} {{ $e->country ? '· '.$e->country : '' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $e->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="shield-check" :title="__('No security events')" :description="__('Login and velocity signals appear here.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-layouts.admin>
