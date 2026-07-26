<x-layouts.admin :title="__('Custom domains')">
    @php
        $canManage = auth('admin')->user()?->can('manage-sellers') || auth('admin')->user()?->hasRole('super-admin');
    @endphp

    <div class="space-y-6">
        <x-ui.page-header :title="__('Custom domains')" :subtitle="__('Every merchant sales-page domain, its verification and SSL state.')" />

        @if (session('success'))
            <x-ui.alert type="success" dismissible>{{ session('success') }}</x-ui.alert>
        @endif

        {{-- Stat cards --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <x-ui.stat-card :label="__('Total')" :value="number_format($stats['total'])" icon="globe-alt" accent="brand" />
            <x-ui.stat-card :label="__('Verified')" :value="number_format($stats['verified'])" icon="check-circle" accent="emerald" />
            <x-ui.stat-card :label="__('Pending')" :value="number_format($stats['pending'])" icon="clock" accent="amber" />
            <x-ui.stat-card :label="__('Failed')" :value="number_format($stats['failed'])" icon="x-circle" accent="rose" />
            <x-ui.stat-card :label="__('SSL active')" :value="number_format($stats['ssl_active'])" icon="lock-closed" accent="emerald" />
            <x-ui.stat-card :label="__('Disabled')" :value="number_format($stats['disabled'])" icon="no-symbol" accent="gray" />
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.shop-domains') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-ui.select name="status" class="w-auto" onchange="this.form.submit()">
                <option value="all" @selected($status === 'all')>{{ __('All statuses') }}</option>
                <option value="pending" @selected($status === 'pending')>{{ __('Pending') }}</option>
                <option value="verifying" @selected($status === 'verifying')>{{ __('Verifying') }}</option>
                <option value="verified" @selected($status === 'verified')>{{ __('Verified') }}</option>
                <option value="failed" @selected($status === 'failed')>{{ __('Failed') }}</option>
                <option value="disabled" @selected($status === 'disabled')>{{ __('Disabled') }}</option>
            </x-ui.select>
            <x-ui.input name="search" :value="$search" icon="magnifying-glass" :placeholder="__('Search domain, owner…')" class="w-full sm:w-72" />
        </form>

        <x-ui.table :headers="[__('Domain'), __('Owner'), __('Page'), __('Verification'), __('SSL'), __('Last checked'), '']">
            @forelse ($domains as $domain)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-3 py-3">
                        <p class="truncate text-sm font-medium text-neutral-900">{{ $domain->host }}</p>
                        <p class="truncate font-mono text-xs text-neutral-400">{{ $domain->dns_record_type?->label() ?? '—' }}
                            @if ($domain->isDisabled())<span class="text-rose-500">· {{ __('disabled') }}</span>@endif
                        </p>
                    </td>
                    <td class="px-3 py-3">
                        <p class="truncate text-sm text-neutral-800">{{ $domain->seller?->user?->name ?? '—' }}</p>
                        <p class="truncate text-xs text-neutral-500">{{ $domain->seller?->user?->email }}</p>
                    </td>
                    <td class="px-3 py-3">
                        <p class="truncate text-sm text-neutral-700">{{ $domain->salesPage?->name ?? '—' }}</p>
                    </td>
                    <td class="px-3 py-3">
                        <x-ui.badge :color="$domain->status->color()" dot>{{ __($domain->status->label()) }}</x-ui.badge>
                        @if ($domain->last_error && ! $domain->isVerified())
                            <p class="mt-1 max-w-[16rem] truncate text-xs text-rose-500" title="{{ $domain->last_error }}">{{ $domain->last_error }}</p>
                        @endif
                    </td>
                    <td class="px-3 py-3"><x-ui.badge :color="$domain->ssl_status->color()">{{ __($domain->ssl_status->label()) }}</x-ui.badge></td>
                    <td class="px-3 py-3 text-sm text-neutral-500">{{ $domain->last_checked_at?->diffForHumans() ?? __('never') }}</td>
                    <td class="px-3 py-3">
                        @if ($canManage)
                            <div class="flex items-center justify-end gap-1.5">
                                <form method="POST" action="{{ route('admin.shop-domains.reverify', $domain->id) }}" class="inline">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm" icon="arrow-path">{{ __('Reverify') }}</x-ui.button>
                                </form>
                                @if ($domain->isDisabled())
                                    <form method="POST" action="{{ route('admin.shop-domains.enable', $domain->id) }}" class="inline">
                                        @csrf
                                        <x-ui.button type="submit" variant="primary" size="sm" icon="check">{{ __('Enable') }}</x-ui.button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.shop-domains.disable', $domain->id) }}" class="inline">
                                        @csrf
                                        <x-ui.button type="submit" variant="danger" size="sm" icon="no-symbol">{{ __('Disable') }}</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="globe-alt" :title="__('No domains')" :description="__('No custom domains match your filters.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $domains->links() }}
    </div>
</x-layouts.admin>
