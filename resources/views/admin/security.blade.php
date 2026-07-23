<x-layouts.admin :title="__('Security')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Security Monitoring')" :subtitle="__('Login anomalies, velocity, whitelist blocks, feature flags & audit-chain integrity.')" />

        {{-- KPIs --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card :label="__('Critical (24h)')" :value="number_format($stats['critical_24h'])" icon="exclamation-triangle" accent="rose" />
            <x-ui.stat-card :label="__('New devices (24h)')" :value="number_format($stats['new_devices_24h'])" icon="computer-desktop" accent="amber" />
            <x-ui.stat-card :label="__('Sign-ins (24h)')" :value="number_format($stats['logins_24h'])" icon="arrow-right-on-rectangle" accent="brand" />
            <div class="pp-card flex items-center justify-between p-5">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ __('Audit chain') }}</p>
                    @if ($stats['chain'])
                        <p class="mt-1 text-lg font-semibold {{ $stats['chain']['ok'] ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $stats['chain']['ok'] ? __('Intact (:count)', ['count' => $stats['chain']['count']]) : __('BROKEN @:at', ['at' => $stats['chain']['brokenAt']]) }}
                        </p>
                    @else
                        <p class="mt-1 text-lg font-semibold text-gray-400">{{ __('Not verified') }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.security.verify-chain') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">{{ __('Verify now') }}</x-ui.button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
        @endif

        {{-- Feature flags --}}
        <x-ui.card :title="__('Security modules')" :subtitle="__('Toggle detection & enforcement. Changes take effect immediately.')">
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($flags as $key => $flag)
                    <form method="POST" action="{{ route('admin.security.flag') }}"
                        class="flex items-center justify-between rounded-lg border border-neutral-200 px-3.5 py-2.5">
                        @csrf
                        <input type="hidden" name="flag" value="{{ $key }}" />
                        <span class="text-sm text-neutral-700">{{ $flag['label'] }}</span>
                        <button class="rounded-full px-3 py-1 text-xs font-semibold {{ $flag['enabled'] ? 'bg-emerald-100 text-emerald-700' : 'bg-neutral-100 text-neutral-500' }}">
                            {{ $flag['enabled'] ? __('On') : __('Off') }}
                        </button>
                    </form>
                @endforeach
            </div>
        </x-ui.card>

        {{-- IP denylist --}}
        <x-ui.card :title="__('IP denylist')" :subtitle="__('Sign-ins and reputation checks flag these addresses.')">
            <form method="POST" action="{{ route('admin.security.ip-denylist') }}" class="flex flex-col gap-2 sm:flex-row">
                @csrf
                <input name="ips" value="{{ implode(', ', $ipDenylist) }}" placeholder="1.2.3.4, 5.6.7.8"
                    class="w-full rounded-lg border-neutral-300 text-sm" />
                <x-ui.button type="submit" variant="primary" size="sm">{{ __('Save') }}</x-ui.button>
            </form>
        </x-ui.card>

        {{-- Events --}}
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold text-neutral-900">{{ __('Security events') }}</h2>
                <form method="GET">
                    <select name="type" onchange="this.form.submit()" class="rounded-lg border-neutral-300 text-xs">
                        @foreach (['all', 'new_device', 'new_location', 'impossible_travel', 'ip_flagged', 'velocity_exceeded', 'whitelist_block', 'address_added', 'insolvency'] as $t)
                            <option value="{{ $t }}" @selected($type === $t)>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @php $sev = ['critical' => 'danger', 'warning' => 'warning', 'info' => 'gray']; @endphp
            <x-ui.table :headers="[__('User'), __('Type'), __('Severity'), __('IP'), __('Risk'), __('When')]">
                @forelse ($events as $e)
                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-neutral-700">{{ $e->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-800">{{ ucfirst(str_replace('_', ' ', $e->type)) }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$sev[$e->severity] ?? 'gray'" dot>{{ ucfirst($e->severity) }}</x-ui.badge></td>
                        <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ $e->ip_address ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-700">{{ $e->risk_score }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $e->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="shield-check" :title="__('No security events')" :description="__('Nothing matches this filter.')" /></td></tr>
                @endforelse
            </x-ui.table>
            <div class="mt-3">{{ $events->links() }}</div>
        </div>
    </div>
</x-layouts.admin>
