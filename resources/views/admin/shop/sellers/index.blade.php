<x-layouts.admin :title="__('Sellers')">
    @php
        $canManage = auth('admin')->user()?->can('manage-sellers') || auth('admin')->user()?->hasRole('super-admin');
        $planColor = fn ($p) => match ($p) { 'business' => 'brand', 'pro' => 'info', default => 'gray' };
        $statusColor = fn ($s) => match ($s->value) {
            'approved' => 'success', 'pending_review' => 'warning',
            'rejected', 'suspended' => 'danger', default => 'gray',
        };
    @endphp

    <div class="space-y-6" x-data="{ planEditingId: null, statusFor: null, statusAction: null, statusLabel: '' }">
        <x-ui.page-header :title="__('Sellers')" :subtitle="__('Manage marketplace sellers, plan tiers and commission.')" />

        {{-- Stat cards --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-ui.stat-card :label="__('Total sellers')" :value="number_format($stats['total'])" icon="rocket-launch" accent="brand" />
            <x-ui.stat-card :label="__('Approved')" :value="number_format($stats['approved'])" icon="check-circle" accent="emerald" />
            <x-ui.stat-card :label="__('Free')" :value="number_format($stats['free'])" icon="user" accent="gray" />
            <x-ui.stat-card :label="__('Pro')" :value="number_format($stats['pro'])" icon="star" accent="brand" />
            <x-ui.stat-card :label="__('Business')" :value="number_format($stats['business'])" icon="building-office" accent="brand" />
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.sellers') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                <x-ui.select name="status" class="w-auto" onchange="this.form.submit()">
                    <option value="all" @selected($status === 'all')>{{ __('All statuses') }}</option>
                    <option value="pending_review" @selected($status === 'pending_review')>{{ __('Pending review') }}</option>
                    <option value="approved" @selected($status === 'approved')>{{ __('Approved') }}</option>
                    <option value="suspended" @selected($status === 'suspended')>{{ __('Suspended') }}</option>
                    <option value="rejected" @selected($status === 'rejected')>{{ __('Rejected') }}</option>
                </x-ui.select>
                <x-ui.select name="plan" class="w-auto" onchange="this.form.submit()">
                    <option value="all" @selected($plan === 'all')>{{ __('All plans') }}</option>
                    <option value="free" @selected($plan === 'free')>{{ __('Free') }}</option>
                    <option value="pro" @selected($plan === 'pro')>{{ __('Pro') }}</option>
                    <option value="business" @selected($plan === 'business')>{{ __('Business') }}</option>
                </x-ui.select>
            </div>

            <x-ui.input name="search" :value="$search" icon="magnifying-glass" :placeholder="__('Search brand, owner…')" class="w-full sm:w-72" />
        </form>

        <x-ui.table :headers="[__('Seller'), __('Owner'), __('Plan'), __('Commission'), __('Status'), __('Joined'), '']">
            @forelse ($sellers as $seller)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$seller->brand_name ?: ($seller->user?->name ?? '?')" size="sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-900">{{ $seller->brand_name ?: __('(no brand)') }}</p>
                                <p class="truncate font-mono text-xs text-neutral-400">{{ $seller->country ?? '—' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <p class="truncate text-sm text-neutral-800">{{ $seller->user?->name ?? '—' }}</p>
                        <p class="truncate text-xs text-neutral-500">{{ $seller->user?->email }}</p>
                    </td>
                    <td class="px-3 py-3"><x-ui.badge :color="$planColor($seller->plan)">{{ ucfirst($seller->plan) }}</x-ui.badge></td>
                    <td class="px-3 py-3 text-sm">
                        <span class="tabular font-semibold text-neutral-900">{{ rtrim(rtrim(number_format($seller->commissionBps() / 100, 2), '0'), '.') }}%</span>
                        @if ($seller->commission_bps === null)
                            <span class="ml-1 text-xs text-neutral-400">{{ __('plan rate') }}</span>
                        @else
                            <span class="ml-1 text-xs text-amber-500">{{ __('override') }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-3"><x-ui.badge :color="$statusColor($seller->status)" dot>{{ $seller->status->label() }}</x-ui.badge></td>
                    <td class="px-3 py-3 text-sm text-neutral-500">{{ $seller->created_at?->diffForHumans() }}</td>
                    <td class="px-3 py-3">
                        <div class="flex items-center justify-end gap-1.5">
                            @if ($canManage)
                                @switch($seller->status)
                                    @case(\App\Shop\Enums\SellerStatus::PendingReview)
                                        <form method="POST" action="{{ route('admin.sellers.status', $seller->id) }}" class="inline">
                                            @csrf <input type="hidden" name="action" value="approve">
                                            <x-ui.button type="submit" variant="primary" size="sm" icon="check">{{ __('Approve') }}</x-ui.button>
                                        </form>
                                        <x-ui.button type="button" variant="danger" size="sm" icon="x-mark"
                                            x-on:click="statusFor='{{ $seller->id }}'; statusAction='reject'; statusLabel='{{ __('Reject application') }}'">{{ __('Reject') }}</x-ui.button>
                                        @break
                                    @case(\App\Shop\Enums\SellerStatus::Approved)
                                        <x-ui.button type="button" variant="danger" size="sm" icon="pause"
                                            x-on:click="statusFor='{{ $seller->id }}'; statusAction='suspend'; statusLabel='{{ __('Suspend seller') }}'">{{ __('Suspend') }}</x-ui.button>
                                        @break
                                    @case(\App\Shop\Enums\SellerStatus::Suspended)
                                    @case(\App\Shop\Enums\SellerStatus::Rejected)
                                        <form method="POST" action="{{ route('admin.sellers.status', $seller->id) }}" class="inline">
                                            @csrf <input type="hidden" name="action" value="reactivate">
                                            <x-ui.button type="submit" variant="primary" size="sm" icon="check">{{ __('Approve') }}</x-ui.button>
                                        </form>
                                        @break
                                @endswitch
                                <x-ui.button type="button" x-on:click="planEditingId = '{{ $seller->id }}'" variant="secondary" size="sm" icon="adjustments-horizontal">{{ __('Plan') }}</x-ui.button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="rocket-launch" :title="__('No sellers')" :description="__('No sellers match your filters.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $sellers->links() }}

        @if ($canManage)
            @foreach ($sellers as $seller)
                {{-- Plan + commission modal --}}
                <div x-show="planEditingId === '{{ $seller->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500/60" x-on:click="planEditingId = null"></div>
                    <div class="relative w-full max-w-md pp-card p-6">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-neutral-900">{{ __('Seller plan') }}</h3>
                                <p class="text-sm text-neutral-500">{{ $seller->brand_name ?: $seller->user?->name }}</p>
                            </div>
                            <button type="button" x-on:click="planEditingId = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                        </div>
                        <form method="POST" action="{{ route('admin.sellers.plan', $seller->id) }}" class="space-y-4">
                            @csrf
                            <x-ui.select :label="__('Plan')" name="plan">
                                @foreach (\App\Shop\Models\Seller::PLANS as $p)
                                    <option value="{{ $p }}" @selected($seller->plan === $p)>{{ ucfirst($p) }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.input :label="__('Commission override (bps)')" name="commission_bps" :value="$seller->commission_bps" type="number" min="0" max="10000" step="1" :placeholder="__('Leave blank for the plan rate')" :error="$errors->first('commission_bps')" />
                            <p class="text-xs text-neutral-500">{{ __('The plan sets the commission rate. An override, if set, wins over the plan. 100 bps = 1%.') }}</p>
                            <div class="flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-on:click="planEditingId = null">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="submit" variant="primary" icon="check">{{ __('Save') }}</x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Reject / suspend modal (optional reason, sent to the seller) --}}
                <div x-show="statusFor === '{{ $seller->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500/60" x-on:click="statusFor = null"></div>
                    <div class="relative w-full max-w-md pp-card p-6">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-neutral-900" x-text="statusLabel"></h3>
                                <p class="text-sm text-neutral-500">{{ $seller->brand_name ?: $seller->user?->name }}</p>
                            </div>
                            <button type="button" x-on:click="statusFor = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                        </div>
                        <form method="POST" action="{{ route('admin.sellers.status', $seller->id) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="action" x-bind:value="statusAction">
                            <x-ui.textarea :label="__('Reason (optional)')" name="reason" rows="3" :placeholder="__('Shared with the seller in their notification.')" />
                            <div class="flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-on:click="statusFor = null">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="submit" variant="danger" icon="check">{{ __('Confirm') }}</x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</x-layouts.admin>
