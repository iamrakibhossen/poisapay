<x-layouts.app :title="__('Deposit history')">
    <div class="space-y-5">
        <x-ui.page-header :title="__('Deposit history')" :subtitle="__('Every deposit into your PaishaPay account.')">
            <x-slot:actions>
                <x-ui.button href="{{ route('deposit.index') }}" icon="plus" size="sm">{{ __('New deposit') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.history-stats :cards="[
            ['label' => __('All-time'), 'value' => number_format($stats['total']), 'icon' => 'clock'],
            ['label' => __('This month'), 'value' => number_format($stats['month']), 'icon' => 'calendar-days'],
            ['label' => __('Total received'), 'value' => $stats['received'], 'icon' => 'arrow-down-left', 'fg' => 'text-emerald-600'],
            ['label' => __('Pending'), 'value' => number_format($stats['pending']), 'icon' => 'clock', 'fg' => $stats['pending'] > 0 ? 'text-amber-600' : 'text-neutral-900'],
        ]" />

        <x-ui.history-filters route="deposit.history" tab-param="status"
            :tabs="['all' => __('All'), 'credited' => __('Credited'), 'pending' => __('Pending'), 'failed' => __('Failed')]"
            :active="$filters['status']" :symbols="$symbols" :asset="$filters['asset']" :search="$filters['search']"
            :search-placeholder="__('Reference or tx hash…')" />

        @if ($deposits->total())
            <x-ui.history-table :columns="[
                ['label' => __('Date')],
                ['label' => __('Deposit')],
                ['label' => __('Source')],
                ['label' => __('Status')],
                ['label' => __('Amount'), 'align' => 'right'],
                ['label' => __('Details'), 'align' => 'right', 'sr' => true],
            ]">
                @foreach ($deposits as $d)
                    @php
                        $at = \Illuminate\Support\Carbon::parse($d['at']);
                        $date = $at->isCurrentYear() ? $at->format('M j') : $at->format('M j, Y');
                    @endphp
                    <tr class="cursor-pointer transition hover:bg-neutral-50/70"
                        role="button" tabindex="0"
                        x-on:click="$dispatch('open-modal', 'deposit-{{ $d['id'] }}')"
                        x-on:keydown.enter="$dispatch('open-modal', 'deposit-{{ $d['id'] }}')"
                        x-on:keydown.space.prevent="$dispatch('open-modal', 'deposit-{{ $d['id'] }}')">
                        <td class="whitespace-nowrap px-5 py-4 align-middle">
                            <p class="text-sm font-medium text-neutral-700">{{ $date }}</p>
                            <p class="text-xs text-neutral-400">{{ $at->format('g:i A') }}</p>
                        </td>
                        <td class="px-5 py-4 align-middle">
                            <div class="flex items-center gap-3">
                                <x-ui.asset-icon :symbol="$d['symbol']" size="md" />
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-neutral-900">{{ $d['symbol'] }}</p>
                                    <p class="truncate text-xs text-neutral-500">{{ $d['network'] }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 align-middle">
                            <p class="text-sm text-neutral-700">{{ $d['source'] }}</p>
                            @if ($d['reference'])
                                <p class="truncate text-xs text-neutral-400">{{ $d['reference'] }}</p>
                            @endif
                            @if ($d['txidShort'])
                                <p class="mt-0.5 font-mono text-xs text-neutral-400" title="{{ $d['txid'] }}">{{ $d['txidShort'] }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4 align-middle">
                            <x-ui.badge :color="$d['statusColor'] ?? 'gray'" dot>{{ $d['status'] }}</x-ui.badge>
                            @if ($d['confirmations'] !== null && $d['status'] !== 'Credited')
                                <p class="mt-1 text-[11px] text-neutral-400">{{ $d['confirmations'] }}/{{ $d['requiredConfirmations'] }} {{ __('confs') }}</p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                            <p class="tabular text-sm font-semibold text-emerald-600">+{{ $d['amount'] }}</p>
                            @if ($d['fee'])
                                <p class="tabular mt-0.5 text-[11px] text-neutral-400">{{ __('fee') }} {{ $d['fee'] }}</p>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                            <x-heroicon-o-chevron-right class="ms-auto h-4 w-4 text-neutral-300" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.history-table>

            {{-- Per-deposit detail modals — the entire record for each deposit. --}}
            @foreach ($deposits as $d)
                @php
                    $at = \Illuminate\Support\Carbon::parse($d['at']);
                    $creditedAt = $d['creditedAt'] ? \Illuminate\Support\Carbon::parse($d['creditedAt']) : null;
                @endphp
                <x-ui.modal name="deposit-{{ $d['id'] }}" :title="__('Deposit details')" :subtitle="$d['symbol'] . ' · ' . $d['network']" maxWidth="lg">
                    <div class="space-y-6">
                        {{-- Headline: amount + status --}}
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <x-ui.asset-icon :symbol="$d['symbol']" size="lg" />
                                <div>
                                    <p class="tabular text-xl font-semibold text-emerald-600">+{{ $d['amount'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $d['name'] }}</p>
                                </div>
                            </div>
                            <x-ui.badge :color="$d['statusColor'] ?? 'gray'" dot>{{ $d['status'] }}</x-ui.badge>
                        </div>

                        {{-- Amounts --}}
                        <x-ui.detail-list>
                            <x-ui.detail-row :label="__('Gross amount')" class="tabular">{{ $d['amount'] }}</x-ui.detail-row>
                            @if ($d['fee'])
                                <x-ui.detail-row :label="__('Platform fee')" class="tabular">−{{ $d['fee'] }}</x-ui.detail-row>
                            @endif
                            <x-ui.detail-row :label="__('Net credited')" class="tabular font-semibold">{{ $d['net'] }}</x-ui.detail-row>
                        </x-ui.detail-list>

                        {{-- Meta --}}
                        <x-ui.detail-list>
                            <x-ui.detail-row :label="__('Source')" :value="$d['source']" />
                            <x-ui.detail-row :label="__('Network')" :value="$d['network']" />
                            @if ($d['reference'])
                                <x-ui.detail-row :label="__('Reference')" class="truncate text-right" :title="$d['reference']">{{ $d['reference'] }}</x-ui.detail-row>
                            @endif
                            @if ($d['confirmations'] !== null)
                                <x-ui.detail-row :label="__('Confirmations')" class="tabular text-right">{{ $d['confirmations'] }} / {{ $d['requiredConfirmations'] }}</x-ui.detail-row>
                            @endif
                            <x-ui.detail-row :label="__('Submitted')" class="text-right" :value="$at->format('M j, Y · g:i A')" />
                            @if ($creditedAt)
                                <x-ui.detail-row :label="__('Credited')" class="text-right" :value="$creditedAt->format('M j, Y · g:i A')" />
                            @endif
                        </x-ui.detail-list>

                        {{-- On-chain details --}}
                        @if ($d['txid'] || $d['from'])
                            <x-ui.detail-list :heading="__('On-chain')">
                                @if ($d['txid'])
                                    <x-ui.detail-row :label="__('Transaction')">
                                        <div class="flex min-w-0 items-center justify-end gap-2">
                                            <span class="truncate font-mono text-xs text-slate-700" title="{{ $d['txid'] }}">{{ $d['txidShort'] }}</span>
                                            <x-ui.copy-text :text="$d['txid']" />
                                            @if ($d['explorer'])
                                                <a href="{{ $d['explorer'] }}" target="_blank" rel="noopener" class="text-brand-600 transition hover:text-brand-700" title="{{ __('View on explorer') }}">
                                                    <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                                                </a>
                                            @endif
                                        </div>
                                    </x-ui.detail-row>
                                @endif
                                @if ($d['from'])
                                    <x-ui.detail-row :label="__('From address')">
                                        <div class="flex min-w-0 items-center justify-end gap-2">
                                            <span class="truncate font-mono text-xs text-slate-700" title="{{ $d['from'] }}">{{ $d['from'] }}</span>
                                            <x-ui.copy-text :text="$d['from']" />
                                        </div>
                                    </x-ui.detail-row>
                                @endif
                            </x-ui.detail-list>
                        @endif

                        <p class="text-center font-mono text-[11px] text-slate-300">{{ $d['id'] }}</p>
                    </div>
                </x-ui.modal>
            @endforeach

            <x-ui.pagination :paginator="$deposits" />
        @elseif ($stats['total'] > 0)
            <div class="pp-card">
                <x-ui.empty-state icon="magnifying-glass" :title="__('No matching deposits')"
                    :description="__('Nothing matches your filters yet.')">
                    <x-slot:action>
                        <x-ui.button href="{{ route('deposit.history') }}" variant="secondary" size="sm">{{ __('Clear filters') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="inbox" :title="__('No deposits yet')"
                    :description="__('Your incoming deposits will appear here.')">
                    <x-slot:action>
                        <x-ui.button href="{{ route('deposit.index') }}" icon="plus" size="sm">{{ __('Make a deposit') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @endif
    </div>
</x-layouts.app>
