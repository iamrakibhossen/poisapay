<x-layouts.app :title="__('Withdrawal history')">
    <div class="space-y-5">
        <x-ui.page-header :title="__('Withdrawal history')" :subtitle="__('Every withdrawal and cash-out from your PoisaPay account.')">
            <x-slot:actions>
                <x-ui.button href="{{ route('withdraw.index') }}" icon="plus" size="sm">{{ __('New withdrawal') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.history-stats :cards="[
            ['label' => __('All-time'), 'value' => number_format($stats['total']), 'icon' => 'clock'],
            ['label' => __('This month'), 'value' => number_format($stats['month']), 'icon' => 'calendar-days'],
            ['label' => __('Total sent'), 'value' => $stats['sent'], 'icon' => 'arrow-up-right'],
            ['label' => __('Pending'), 'value' => number_format($stats['pending']), 'icon' => 'clock', 'fg' => $stats['pending'] > 0 ? 'text-amber-600' : 'text-neutral-900'],
        ]" />

        <x-ui.history-filters route="withdraw.history" tab-param="status"
            :tabs="['all' => __('All'), 'completed' => __('Completed'), 'pending' => __('Pending'), 'failed' => __('Failed')]"
            :active="$filters['status']" :symbols="$symbols" :asset="$filters['asset']" :search="$filters['search']"
            :search-placeholder="__('Destination or tx hash…')" />

        @if ($withdrawals->total())
            <x-ui.history-table :columns="[
                ['label' => __('Date')],
                ['label' => __('Withdrawal')],
                ['label' => __('Destination')],
                ['label' => __('Status')],
                ['label' => __('Amount'), 'align' => 'right'],
                ['label' => __('Details'), 'align' => 'right', 'sr' => true],
            ]">
                @foreach ($withdrawals as $w)
                    @php
                        $at = \Illuminate\Support\Carbon::parse($w['at']);
                        $date = $at->isCurrentYear() ? $at->format('M j') : $at->format('M j, Y');
                    @endphp
                    <tr class="cursor-pointer transition hover:bg-neutral-50/70"
                        role="button" tabindex="0"
                        x-on:click="$dispatch('open-modal', 'withdrawal-{{ $w['id'] }}')"
                        x-on:keydown.enter="$dispatch('open-modal', 'withdrawal-{{ $w['id'] }}')"
                        x-on:keydown.space.prevent="$dispatch('open-modal', 'withdrawal-{{ $w['id'] }}')">
                        <td class="whitespace-nowrap px-5 py-4 align-middle">
                            <p class="text-sm font-medium text-neutral-700">{{ $date }}</p>
                            <p class="text-xs text-neutral-400">{{ $at->format('g:i A') }}</p>
                        </td>
                        <td class="px-5 py-4 align-middle">
                            <div class="flex items-center gap-3">
                                <x-ui.asset-icon :symbol="$w['symbol']" size="md" />
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-neutral-900">{{ $w['symbol'] }}</p>
                                    <p class="truncate text-xs text-neutral-500">{{ $w['network'] }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 align-middle">
                            <p class="truncate font-mono text-xs text-neutral-500">{{ $w['to'] ?? '—' }}</p>
                            @if ($w['txidShort'])
                                <p class="mt-0.5 font-mono text-xs text-neutral-400" title="{{ $w['txid'] }}">{{ $w['txidShort'] }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4 align-middle">
                            <x-ui.badge :color="$w['statusColor'] ?? 'gray'" dot>{{ $w['status'] }}</x-ui.badge>
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                            <p class="tabular text-sm font-semibold text-neutral-900">-{{ $w['amount'] }}</p>
                            @if ($w['fee'])
                                <p class="tabular mt-0.5 text-[11px] text-neutral-400">{{ __('fee') }} {{ $w['fee'] }}</p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                            <x-heroicon-o-chevron-right class="ms-auto h-4 w-4 text-neutral-300" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.history-table>

            {{-- Per-withdrawal detail modals — the entire record for each withdrawal. --}}
            @foreach ($withdrawals as $w)
                @php
                    $at = \Illuminate\Support\Carbon::parse($w['at']);
                    $completedAt = $w['completedAt'] ? \Illuminate\Support\Carbon::parse($w['completedAt']) : null;
                @endphp
                <x-ui.modal name="withdrawal-{{ $w['id'] }}" :title="__('Withdrawal details')" :subtitle="$w['symbol'] . ' · ' . $w['network']" maxWidth="lg">
                    <div class="space-y-6">
                        {{-- Headline: amount + status --}}
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <x-ui.asset-icon :symbol="$w['symbol']" size="lg" />
                                <div>
                                    <p class="tabular text-xl font-semibold text-neutral-900">-{{ $w['amount'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $w['name'] }}</p>
                                </div>
                            </div>
                            <x-ui.badge :color="$w['statusColor'] ?? 'gray'" dot>{{ $w['status'] }}</x-ui.badge>
                        </div>

                        {{-- Amounts --}}
                        <x-ui.detail-list>
                            <x-ui.detail-row :label="__('Amount')" class="tabular">{{ $w['amount'] }}</x-ui.detail-row>
                            @if ($w['fee'])
                                <x-ui.detail-row :label="__('Fee')" class="tabular">{{ $w['fee'] }}</x-ui.detail-row>
                            @endif
                            <x-ui.detail-row :label="__('Total debited')" class="tabular font-semibold">{{ $w['total'] }}</x-ui.detail-row>
                        </x-ui.detail-list>

                        {{-- Meta --}}
                        <x-ui.detail-list>
                            <x-ui.detail-row :label="__('Network')" :value="$w['network']" />
                            @if ($w['payoutMethod'])
                                <x-ui.detail-row :label="__('Payout method')" class="capitalize" :value="$w['payoutMethod']" />
                            @endif
                            @if ($w['toFull'])
                                <x-ui.detail-row :label="$w['isFiat'] ? __('Destination') : __('To address')">
                                    <div class="flex min-w-0 items-center justify-end gap-2">
                                        <span class="truncate font-mono text-xs text-slate-700" title="{{ $w['toFull'] }}">{{ $w['toFull'] }}</span>
                                        <x-ui.copy-text :text="$w['toFull']" />
                                    </div>
                                </x-ui.detail-row>
                            @endif
                            @if ($w['riskLevel'])
                                <x-ui.detail-row :label="__('Risk level')" :value="$w['riskLevel']" />
                            @endif
                            <x-ui.detail-row :label="__('Requested')" class="text-right" :value="$at->format('M j, Y · g:i A')" />
                            @if ($completedAt)
                                <x-ui.detail-row :label="__('Completed')" class="text-right" :value="$completedAt->format('M j, Y · g:i A')" />
                            @endif
                        </x-ui.detail-list>

                        {{-- Failure reason --}}
                        @if ($w['failureReason'])
                            <div class="rounded-xl border border-rose-100 bg-rose-50/60 px-4 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-rose-400">{{ __('Failure reason') }}</p>
                                <p class="mt-1 text-sm text-rose-700">{{ $w['failureReason'] }}</p>
                            </div>
                        @endif

                        {{-- On-chain details --}}
                        @if ($w['txid'])
                            <x-ui.detail-list :heading="__('On-chain')">
                                <x-ui.detail-row :label="__('Transaction')">
                                    <div class="flex min-w-0 items-center justify-end gap-2">
                                        <span class="truncate font-mono text-xs text-slate-700" title="{{ $w['txid'] }}">{{ $w['txidShort'] }}</span>
                                        <x-ui.copy-text :text="$w['txid']" />
                                        @if ($w['explorer'])
                                            <a href="{{ $w['explorer'] }}" target="_blank" rel="noopener" class="text-brand-600 transition hover:text-brand-700" title="{{ __('View on explorer') }}">
                                                <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                                            </a>
                                        @endif
                                    </div>
                                </x-ui.detail-row>
                            </x-ui.detail-list>
                        @endif

                        <p class="text-center font-mono text-[11px] text-slate-300">{{ $w['id'] }}</p>
                    </div>
                </x-ui.modal>
            @endforeach

            <x-ui.pagination :paginator="$withdrawals" />
        @elseif ($stats['total'] > 0)
            <div class="pp-card">
                <x-ui.empty-state icon="magnifying-glass" :title="__('No matching withdrawals')"
                    :description="__('Nothing matches your filters yet.')">
                    <x-slot:action>
                        <x-ui.button href="{{ route('withdraw.history') }}" variant="secondary" size="sm">{{ __('Clear filters') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="arrow-up-tray" :title="__('No withdrawals yet')"
                    :description="__('Your withdrawal requests will appear here.')">
                    <x-slot:action>
                        <x-ui.button href="{{ route('withdraw.index') }}" icon="plus" size="sm">{{ __('Make a withdrawal') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @endif
    </div>
</x-layouts.app>
