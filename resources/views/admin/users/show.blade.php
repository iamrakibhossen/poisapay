<x-layouts.admin :title="$user->name">
    @php
        $admin = auth('admin')->user();
        $allow = fn ($p) => $admin->hasRole('super-admin') || $admin->can($p);
    @endphp

    <div class="space-y-6">
        <x-ui.page-header :title="$user->name" :subtitle="$user->email">
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.users') }}" variant="ghost" size="sm" icon="arrow-left">{{ __('Back') }}</x-ui.button>

                @if ($allow('manage-users'))
                    <x-ui.button href="{{ route('admin.users.edit', $user) }}" variant="secondary" size="sm" icon="pencil-square">{{ __('Edit') }}</x-ui.button>
                @endif

                @if ($allow('impersonate-users'))
                    <form method="POST" action="{{ route('admin.impersonate', $user->id) }}"
                        onsubmit="return confirm('View the app as {{ $user->name }}? Your operator session resumes when you stop.');">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm" icon="eye">{{ __('View as') }}</x-ui.button>
                    </form>
                @endif

                @if ($allow('adjust-balance'))
                    <x-ui.button type="button" size="sm" icon="banknotes" x-on:click="$dispatch('open-modal', 'adjust-balance')">{{ __('Adjust balance') }}</x-ui.button>
                @endif

                @if ($allow('freeze-users'))
                    <form method="POST" action="{{ route('admin.users.freeze', $user->id) }}"
                        onsubmit="return confirm('{{ $user->is_frozen ? 'Unfreeze '.$user->name.' and restore money movement?' : 'Freeze '.$user->name.' and block money movement?' }}')">
                        @csrf
                        @if ($user->is_frozen)
                            <x-ui.button type="submit" variant="secondary" size="sm" icon="lock-open">{{ __('Unfreeze') }}</x-ui.button>
                        @else
                            <x-ui.button type="submit" variant="danger" size="sm" icon="lock-closed">{{ __('Freeze') }}</x-ui.button>
                        @endif
                    </form>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Frozen banner --}}
        @if ($user->is_frozen)
            <x-ui.alert type="danger" :title="__('Account frozen')">{{ __('Money movement is blocked for this account. Deposits still credit, but withdrawals, transfers and card spend are disabled.') }}</x-ui.alert>
        @endif

        {{-- Identity card --}}
        <div class="pp-card p-6">
            <div class="flex flex-col gap-4 border-b border-gray-100 pb-6 sm:flex-row sm:items-center">
                <x-ui.avatar :name="$user->name" size="lg" />
                <div class="min-w-0 flex-1">
                    <h2 class="flex items-center gap-2 text-lg font-semibold text-neutral-900">
                        {{ $user->name }}
                        @if ($user->is_frozen)<x-heroicon-s-lock-closed class="h-4 w-4 text-rose-500" />@endif
                    </h2>
                    <p class="text-sm text-neutral-500">{{ $user->email }}</p>
                    <p class="mt-0.5 text-xs text-neutral-400">{{ __('ID') }} {{ $user->uid }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.badge :color="$user->kyc_tier->color()">{{ $user->kyc_tier->label() }}</x-ui.badge>
                    <x-ui.badge :color="$user->kyc_status->color()" dot>{{ __('KYC:') }} {{ $user->kyc_status->label() }}</x-ui.badge>
                    <x-ui.badge :color="$user->email_verified_at ? 'success' : 'gray'">{{ $user->email_verified_at ? __('Email verified') : __('Email unverified') }}</x-ui.badge>
                    @if ($user->hasTwoFactorEnabled())<x-ui.badge color="success">{{ __('2FA on') }}</x-ui.badge>@endif
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-x-8 gap-y-4 pt-6 sm:grid-cols-4">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Phone') }}</dt>
                    <dd class="mt-0.5 font-medium text-neutral-900">{{ $user->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Base currency') }}</dt>
                    <dd class="mt-0.5 font-medium text-neutral-900">{{ $user->base_currency ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Referral code') }}</dt>
                    <dd class="mt-0.5 font-medium text-neutral-900">{{ $user->referral_code ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Joined') }}</dt>
                    <dd class="mt-0.5 font-medium text-neutral-900">{{ $user->created_at->format('M j, Y') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Activity counts --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-ui.stat-card :label="__('Deposits')" :value="number_format($stats['deposits'])" icon="arrow-down-tray" accent="emerald" />
            <x-ui.stat-card :label="__('Withdrawals')" :value="number_format($stats['withdrawals'])" icon="arrow-up-tray" accent="rose" />
            <x-ui.stat-card :label="__('Transfers')" :value="number_format($stats['transfers'])" icon="arrows-right-left" accent="brand" />
            <x-ui.stat-card :label="__('Cards')" :value="number_format($stats['cards'])" icon="credit-card" accent="amber" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Balances --}}
            <div class="pp-card p-6">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ __('Wallet balances') }}</h3>
                @if ($balances->isNotEmpty())
                    <div class="overflow-hidden rounded-xl border border-neutral-200">
                        <table class="w-full text-sm">
                            <thead class="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">{{ __('Asset') }}</th>
                                    <th class="px-4 py-2 text-right font-semibold">{{ __('Available') }}</th>
                                    <th class="px-4 py-2 text-right font-semibold">{{ __('Locked') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @foreach ($balances as $row)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <x-ui.asset-icon :symbol="$row['asset']->symbol" size="sm" />
                                                <span class="font-medium text-neutral-900">{{ $row['asset']->symbol }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold tabular text-neutral-900">{{ $row['available']->format() }}</td>
                                        <td class="px-4 py-3 text-right tabular {{ $row['locked']->isZero() ? 'text-neutral-400' : 'text-amber-600' }}">{{ $row['locked']->format() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-neutral-500">{{ __('No non-zero balances.') }}</p>
                @endif
            </div>

            {{-- KYC --}}
            <div class="pp-card p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ __('KYC') }}</h3>
                    @if ($allow('review-kyc'))
                        <a href="{{ route('admin.kyc') }}" class="text-sm font-medium text-brand-700 hover:text-brand-800">{{ __('Review queue') }}</a>
                    @endif
                </div>
                @if ($kyc)
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Status') }}</dt><dd><x-ui.badge :color="$kyc->status->color()">{{ $kyc->status->label() }}</x-ui.badge></dd></div>
                        <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Requested tier') }}</dt><dd class="font-medium text-neutral-900">{{ $kyc->requested_tier->label() }}</dd></div>
                        <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Full name') }}</dt><dd class="font-medium text-neutral-900">{{ $kyc->full_name ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Document') }}</dt><dd class="font-medium text-neutral-900">{{ $kyc->document_type ? ucfirst(str_replace('_', ' ', $kyc->document_type)) : '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Country') }}</dt><dd class="font-medium text-neutral-900">{{ $kyc->country ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-neutral-500">{{ __('Submitted') }}</dt><dd class="font-medium text-neutral-900">{{ $kyc->created_at->format('M j, Y') }}</dd></div>
                        @if ($kyc->rejection_reason)
                            <div class="pt-1 text-rose-700">{{ __('Rejected:') }} {{ $kyc->rejection_reason }}</div>
                        @endif
                    </dl>
                @else
                    <p class="text-sm text-neutral-500">{{ __('No KYC submission yet.') }}</p>
                @endif
            </div>
        </div>

        {{-- Recent activity --}}
        <div class="pp-card p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ __('Recent activity') }}</h3>
                @if ($allow('view-activity-logs'))
                    <a href="{{ route('admin.activity-logs') }}" class="text-sm font-medium text-brand-700 hover:text-brand-800">{{ __('View all') }}</a>
                @endif
            </div>
            @forelse ($recentActivity as $log)
                <div class="flex items-start gap-3 border-b border-neutral-50 py-2.5 last:border-0">
                    <x-heroicon-o-clock class="mt-0.5 h-4 w-4 shrink-0 text-neutral-400" />
                    <div class="min-w-0 text-sm">
                        <p class="text-neutral-800">{{ $log->description ?? $log->action }}</p>
                        <p class="text-xs text-neutral-400">{{ $log->actor_name ?? __('System') }} · {{ $log->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-neutral-500">{{ __('No recorded activity for this user.') }}</p>
            @endforelse
        </div>
    </div>

    {{-- Balance adjustment modal --}}
    @if ($allow('adjust-balance'))
        <x-ui.modal name="adjust-balance" :title="__('Adjust balance')" maxWidth="lg">
            <form method="POST" action="{{ route('admin.users.balance', $user) }}"
                x-data="{ type: '{{ old('type', 'credit') }}' }" class="space-y-4 text-left">
                @csrf

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" x-on:click="type = 'credit'"
                        :class="type === 'credit' ? 'border-emerald-500 bg-emerald-50 text-emerald-700 ring-1 ring-emerald-500' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50'"
                        class="flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-semibold transition">
                        <x-heroicon-o-plus-circle class="h-5 w-5" /> {{ __('Credit') }}
                    </button>
                    <button type="button" x-on:click="type = 'debit'"
                        :class="type === 'debit' ? 'border-rose-500 bg-rose-50 text-rose-700 ring-1 ring-rose-500' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50'"
                        class="flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-semibold transition">
                        <x-heroicon-o-minus-circle class="h-5 w-5" /> {{ __('Debit') }}
                    </button>
                </div>
                <input type="hidden" name="type" :value="type" />

                <x-ui.select :label="__('Asset')" name="asset_id" :error="$errors->first('asset_id')">
                    @foreach ($assets as $asset)
                        <option value="{{ $asset->id }}" @selected(old('asset_id') == $asset->id)>{{ $asset->symbol }} — {{ $asset->name }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.input :label="__('Amount')" name="amount" type="number" step="any" min="0" :value="old('amount')"
                    placeholder="{{ __('0.00') }}" :error="$errors->first('amount')" />

                <x-ui.input :label="__('Reason / note')" name="reason" :value="old('reason')" maxlength="255"
                    placeholder="{{ __('Recorded in the ledger and audit log') }}" :error="$errors->first('reason')" />

                <p class="rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-500">
                    {{ __('Posts a balanced double-entry against') }} <span class="font-medium">treasury:pending</span> {{ __('so ledger solvency stays intact. Debits cannot exceed the available balance.') }}
                </p>

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'adjust-balance')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" x-text="type === 'credit' ? 'Credit balance' : 'Debit balance'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->hasAny(['asset_id', 'amount', 'reason', 'type']))
            <script>
                document.addEventListener('alpine:initialized', () =>
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'adjust-balance' })));
            </script>
        @endif
    @endif
</x-layouts.admin>
