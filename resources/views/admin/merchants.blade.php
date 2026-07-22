<x-layouts.admin :title="'Merchants'">
    @php
        $canManage = auth('admin')->user()?->can('manage-merchants') || auth('admin')->user()?->hasRole('super-admin');
    @endphp

    <div class="space-y-6" x-data="{ viewingId: null, suspendingId: null, feeEditingId: null }">
        <x-ui.page-header title="Merchants" subtitle="Approve, monitor and suspend merchant accounts." />

        {{-- Stat cards --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-ui.stat-card label="Total merchants" :value="number_format($stats['total'])" icon="building-storefront" accent="brand" />
            <x-ui.stat-card label="Active" :value="number_format($stats['active'])" icon="check-circle" accent="emerald" />
            <x-ui.stat-card label="Needs review" :value="number_format($stats['pending'])" icon="clock" accent="amber" />
            <x-ui.stat-card label="Suspended" :value="number_format($stats['suspended'])" icon="no-symbol" accent="rose" />
            <x-ui.stat-card label="Paid volume" :value="number_format($stats['volume'] / 100, 2)" icon="banknotes" accent="brand" />
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.merchants') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                <x-ui.select name="status" class="w-auto" onchange="this.form.submit()">
                    <option value="all" @selected($status === 'all')>All statuses</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="suspended" @selected($status === 'suspended')>Suspended</option>
                </x-ui.select>
            </div>

            <x-ui.input name="search" :value="$search" icon="magnifying-glass" placeholder="Search business, slug, owner…" class="w-full sm:w-72" />
        </form>

        <x-ui.table :headers="['Business', 'Owner', 'Category', 'Fee', 'Status', 'Invoices', 'Joined', '']">
            @forelse ($merchants as $merchant)
                @php
                    $agg = $paidAgg[$merchant->user_id] ?? null;
                    $totalCount = (int) ($totalCounts[$merchant->user_id]->cnt ?? 0);
                    $paidVolume = $merchant->settlementAsset
                        ? $merchant->settlementAsset->money((string) ($agg->gross ?? '0'))->format(2)
                        : number_format(((int) ($agg->gross ?? 0)) / 100, 2);
                @endphp
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$merchant->business_name" size="sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-900">{{ $merchant->business_name }}</p>
                                <p class="truncate font-mono text-xs text-neutral-400">{{ $merchant->slug }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <p class="truncate text-sm text-neutral-800">{{ $merchant->user?->name ?? '—' }}</p>
                        <p class="truncate text-xs text-neutral-500">{{ $merchant->user?->email }}</p>
                    </td>
                    <td class="px-3 py-3 text-sm text-neutral-600">{{ $merchant->category ?? '—' }}</td>
                    <td class="px-3 py-3 text-sm">
                        <span class="tabular font-semibold text-neutral-900">{{ rtrim(rtrim(number_format($merchant->feeBps() / 100, 2), '0'), '.') }}%</span>
                        @if ($merchant->fee_bps === null)
                            <span class="ml-1 text-xs text-neutral-400">default</span>
                        @endif
                    </td>
                    <td class="px-3 py-3"><x-ui.badge :color="$merchant->status->color()" dot>{{ $merchant->status->label() }}</x-ui.badge></td>
                    <td class="px-3 py-3 text-sm text-neutral-600">
                        <span class="tabular">{{ number_format($totalCount) }}</span> total
                        <span class="block text-xs text-neutral-400">{{ $paidVolume }} paid</span>
                    </td>
                    <td class="px-3 py-3 text-sm text-neutral-500">{{ $merchant->created_at?->diffForHumans() }}</td>
                    <td class="px-3 py-3">
                        <div class="flex items-center justify-end gap-1.5">
                            @if ($canManage)
                                @if ($merchant->status === \App\Enums\MerchantStatus::Pending)
                                    <form method="POST" action="{{ route('admin.merchants.approve', $merchant->id) }}"
                                        onsubmit="return confirm('Approve this merchant and activate their account?')">
                                        @csrf
                                        <x-ui.button type="submit" variant="primary" size="sm" icon="check">Approve</x-ui.button>
                                    </form>
                                @elseif ($merchant->status === \App\Enums\MerchantStatus::Active)
                                    <x-ui.button type="button" x-on:click="suspendingId = '{{ $merchant->id }}'" variant="danger" size="sm" icon="no-symbol">Suspend</x-ui.button>
                                @elseif ($merchant->status === \App\Enums\MerchantStatus::Suspended)
                                    <form method="POST" action="{{ route('admin.merchants.reactivate', $merchant->id) }}"
                                        onsubmit="return confirm('Reactivate this merchant?')">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary" size="sm" icon="arrow-path">Reactivate</x-ui.button>
                                    </form>
                                @endif
                                <x-ui.button type="button" x-on:click="feeEditingId = '{{ $merchant->id }}'" variant="ghost" size="sm" icon="adjustments-horizontal">Fee</x-ui.button>
                            @endif
                            <x-ui.button type="button" x-on:click="viewingId = '{{ $merchant->id }}'" variant="secondary" size="sm" icon="eye">View</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-ui.empty-state icon="building-storefront" title="No merchants" description="No merchant accounts match your filters." /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $merchants->links() }}

        @foreach ($merchants as $merchant)
            {{-- Suspend reason modal --}}
            @if ($canManage)
                <div x-show="suspendingId === '{{ $merchant->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500/60" x-on:click="suspendingId = null"></div>
                    <div class="relative w-full max-w-md pp-card p-6">
                        <div class="mb-4 flex items-start justify-between">
                            <h3 class="text-lg font-semibold text-neutral-900">Suspend merchant</h3>
                            <button type="button" x-on:click="suspendingId = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                        </div>
                        <p class="mb-4 text-sm text-neutral-500">Suspending blocks the merchant from accepting new payments. Provide a reason — it is shown to the merchant.</p>
                        <form method="POST" action="{{ route('admin.merchants.suspend', $merchant->id) }}" class="space-y-4">
                            @csrf
                            <x-ui.textarea label="Reason" name="suspendReason" :rows="3" placeholder="e.g. Pending compliance review" :error="$errors->first('suspendReason')" />
                            <div class="flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-on:click="suspendingId = null">Cancel</x-ui.button>
                                <x-ui.button type="submit" variant="danger" icon="no-symbol">Suspend</x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Fee modal --}}
                <div x-show="feeEditingId === '{{ $merchant->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500/60" x-on:click="feeEditingId = null"></div>
                    <div class="relative w-full max-w-md pp-card p-6">
                        <div class="mb-4 flex items-start justify-between">
                            <h3 class="text-lg font-semibold text-neutral-900">Processing fee</h3>
                            <button type="button" x-on:click="feeEditingId = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                        </div>
                        <p class="mb-4 text-sm text-neutral-500">Fee in basis points (0–10000). 100 bps = 1%. Leave blank to reset to the platform default.</p>
                        <form method="POST" action="{{ route('admin.merchants.fee', $merchant->id) }}" class="space-y-4">
                            @csrf
                            <x-ui.input label="Fee (bps)" name="feeInput" :value="$merchant->fee_bps" type="number" min="0" max="10000" step="1" placeholder="Default" :error="$errors->first('feeInput')" />
                            <div class="flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-on:click="feeEditingId = null">Cancel</x-ui.button>
                                <x-ui.button type="submit" variant="primary" icon="check">Save fee</x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Detail modal --}}
            <div x-show="viewingId === '{{ $merchant->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="viewingId = null"></div>
                <div class="relative flex max-h-[85vh] w-full max-w-2xl flex-col pp-card p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-neutral-900">{{ $merchant->business_name }}</h3>
                            <p class="text-sm text-neutral-500">
                                {{ $merchant->user?->name }} · {{ $merchant->user?->email }}
                            </p>
                        </div>
                        <button type="button" x-on:click="viewingId = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>

                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <x-ui.badge :color="$merchant->status->color()" dot>{{ $merchant->status->label() }}</x-ui.badge>
                        @if ($merchant->category)<x-ui.badge color="gray">{{ $merchant->category }}</x-ui.badge>@endif
                        <span class="tabular text-xs text-neutral-500">Fee {{ rtrim(rtrim(number_format($merchant->feeBps() / 100, 2), '0'), '.') }}%</span>
                        @if ($merchant->approved_at)<span class="text-xs text-neutral-400">Approved {{ $merchant->approved_at?->diffForHumans() }}</span>@endif
                    </div>

                    @if ($merchant->status === \App\Enums\MerchantStatus::Suspended && $merchant->suspension_reason)
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                            <span class="font-semibold">Suspension reason:</span> {{ $merchant->suspension_reason }}
                        </div>
                    @endif

                    {{-- Profile details --}}
                    <dl class="mb-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Website</dt>
                            <dd class="mt-0.5 truncate text-neutral-800">
                                @if ($merchant->website)<a href="{{ $merchant->website }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">{{ $merchant->website }}</a>@else — @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Support email</dt>
                            <dd class="mt-0.5 truncate text-neutral-800">{{ $merchant->support_email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Statement descriptor</dt>
                            <dd class="mt-0.5 truncate font-mono text-neutral-800">{{ $merchant->statement_descriptor ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Settlement asset</dt>
                            <dd class="mt-0.5 text-neutral-800">{{ $merchant->settlementAsset?->symbol ?? '—' }}</dd>
                        </div>
                    </dl>

                    @if ($canManage)
                        <div class="mb-4 flex flex-wrap items-center gap-2">
                            @if ($merchant->status === \App\Enums\MerchantStatus::Pending)
                                <form method="POST" action="{{ route('admin.merchants.approve', $merchant->id) }}"
                                    onsubmit="return confirm('Approve this merchant and activate their account?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="primary" size="sm" icon="check">Approve</x-ui.button>
                                </form>
                            @elseif ($merchant->status === \App\Enums\MerchantStatus::Active)
                                <x-ui.button type="button" x-on:click="viewingId = null; suspendingId = '{{ $merchant->id }}'" variant="danger" size="sm" icon="no-symbol">Suspend</x-ui.button>
                            @elseif ($merchant->status === \App\Enums\MerchantStatus::Suspended)
                                <form method="POST" action="{{ route('admin.merchants.reactivate', $merchant->id) }}"
                                    onsubmit="return confirm('Reactivate this merchant?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm" icon="arrow-path">Reactivate</x-ui.button>
                                </form>
                            @endif
                            <x-ui.button type="button" x-on:click="viewingId = null; feeEditingId = '{{ $merchant->id }}'" variant="ghost" size="sm" icon="adjustments-horizontal">Set fee</x-ui.button>
                        </div>
                    @endif

                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">Recent invoices</p>
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <x-ui.table :headers="['Reference', 'Amount', 'Fee', 'Net', 'Status', 'Date']">
                            @forelse (($invoices[$merchant->user_id] ?? collect())->take(15) as $invoice)
                                <tr class="border-b border-gray-200 hover:bg-gray-100">
                                    <td class="px-3 py-2.5 font-mono text-xs text-neutral-600">{{ $invoice->reference ?? '—' }}</td>
                                    <td class="px-3 py-2.5 tabular text-sm font-semibold text-neutral-900">{{ $invoice->money()->format(2) }}</td>
                                    <td class="px-3 py-2.5 tabular text-sm text-neutral-600">{{ $invoice->feeMoney()->format(2) }}</td>
                                    <td class="px-3 py-2.5 tabular text-sm text-neutral-800">{{ $invoice->netMoney()->format(2) }}</td>
                                    <td class="px-3 py-2.5"><x-ui.badge :color="match ($invoice->status) { 'paid' => 'success', 'pending' => 'warning', 'refunded' => 'info', 'cancelled', 'expired' => 'danger', default => 'gray' }">{{ ucfirst($invoice->status) }}</x-ui.badge></td>
                                    <td class="px-3 py-2.5 text-xs text-neutral-500">{{ $invoice->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-ui.empty-state icon="receipt-percent" title="No invoices" description="This merchant has no invoices yet." /></td></tr>
                            @endforelse
                        </x-ui.table>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <x-ui.button type="button" variant="secondary" x-on:click="viewingId = null">Close</x-ui.button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
