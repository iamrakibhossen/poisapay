<x-layouts.app :title="__('Transfer history')">
    <div class="space-y-5">
        <x-ui.page-header :title="__('Transfer history')" :subtitle="__('Money you\'ve sent to and received from other PaishaPay users.')">
            <x-slot:actions>
                <x-ui.button href="{{ route('send.index') }}" icon="plus" size="sm">{{ __('New transfer') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.history-stats :cards="[
            ['label' => __('All-time'), 'value' => number_format($stats['total']), 'icon' => 'clock'],
            ['label' => __('This month'), 'value' => number_format($stats['month']), 'icon' => 'calendar-days'],
            ['label' => __('Total sent'), 'value' => $stats['sent'], 'icon' => 'arrow-up-right'],
            ['label' => __('Total received'), 'value' => $stats['received'], 'icon' => 'arrow-down-left', 'fg' => 'text-emerald-600'],
        ]" />

        <x-ui.history-filters route="send.history" tab-param="direction"
            :tabs="['all' => __('All'), 'sent' => __('Sent'), 'received' => __('Received')]"
            :active="$filters['direction']" :symbols="$symbols" :asset="$filters['asset']" :search="$filters['search']"
            :search-placeholder="__('Name or memo…')" />

        @if ($transfers->total())
            <x-ui.history-table :columns="[
                ['label' => __('Date')],
                ['label' => __('Transfer')],
                ['label' => __('Memo')],
                ['label' => __('Amount'), 'align' => 'right'],
                ['label' => __('Details'), 'align' => 'right', 'sr' => true],
            ]">
                @foreach ($transfers as $t)
                    @php
                        $at = \Illuminate\Support\Carbon::parse($t['at']);
                        $date = $at->isCurrentYear() ? $at->format('M j') : $at->format('M j, Y');
                    @endphp
                    <tr class="cursor-pointer transition hover:bg-neutral-50/70"
                        role="button" tabindex="0"
                        x-on:click="$dispatch('open-modal', 'transfer-{{ $t['id'] }}')"
                        x-on:keydown.enter="$dispatch('open-modal', 'transfer-{{ $t['id'] }}')"
                        x-on:keydown.space.prevent="$dispatch('open-modal', 'transfer-{{ $t['id'] }}')">
                        <td class="whitespace-nowrap px-5 py-4 align-middle">
                            <p class="text-sm font-medium text-neutral-700">{{ $date }}</p>
                            <p class="text-xs text-neutral-400">{{ $at->format('g:i A') }}</p>
                        </td>
                        <td class="px-5 py-4 align-middle">
                            <div class="flex items-center gap-3">
                                <span @class([
                                    'grid h-9 w-9 shrink-0 place-items-center rounded-lg',
                                    'bg-neutral-100 text-neutral-500' => $t['sent'],
                                    'bg-emerald-50 text-emerald-600' => ! $t['sent'],
                                ])>
                                    <x-dynamic-component :component="'heroicon-o-arrow-'.($t['sent'] ? 'up-right' : 'down-left')" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-neutral-900">{{ $t['sent'] ? __('Sent') : __('Received') }} · {{ $t['symbol'] }}</p>
                                    <p class="truncate text-xs text-neutral-500">{{ $t['sent'] ? __('To') : __('From') }} {{ $t['counterparty'] }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 align-middle">
                            <p class="truncate text-xs text-neutral-500">{{ $t['memo'] ?: '—' }}</p>
                        </td>
                        <td class="tabular whitespace-nowrap px-5 py-4 text-right align-middle text-sm font-semibold {{ $t['sent'] ? 'text-neutral-900' : 'text-emerald-600' }}">
                            {{ $t['sent'] ? '-' : '+' }}{{ $t['amount'] }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                            <x-heroicon-o-chevron-right class="ms-auto h-4 w-4 text-neutral-300" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.history-table>

            {{-- Per-transfer detail modals — the entire record for each transfer. --}}
            @foreach ($transfers as $t)
                @php $at = \Illuminate\Support\Carbon::parse($t['at']); @endphp
                <x-ui.modal name="transfer-{{ $t['id'] }}" :title="__('Transfer details')" :subtitle="($t['sent'] ? __('Sent') : __('Received')) . ' · ' . $t['symbol']" maxWidth="lg">
                    <div class="space-y-6">
                        {{-- Headline: amount + status --}}
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <span @class([
                                    'grid h-11 w-11 shrink-0 place-items-center rounded-full',
                                    'bg-neutral-100 text-neutral-500' => $t['sent'],
                                    'bg-emerald-50 text-emerald-600' => ! $t['sent'],
                                ])>
                                    <x-dynamic-component :component="'heroicon-o-arrow-'.($t['sent'] ? 'up-right' : 'down-left')" class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="tabular text-xl font-semibold {{ $t['sent'] ? 'text-neutral-900' : 'text-emerald-600' }}">{{ $t['sent'] ? '-' : '+' }}{{ $t['amount'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $t['name'] }}</p>
                                </div>
                            </div>
                            @if ($t['status'])
                                <x-ui.badge :color="$t['statusColor'] ?? 'gray'" dot>{{ $t['status'] }}</x-ui.badge>
                            @endif
                        </div>

                        {{-- Details --}}
                        <x-ui.detail-list>
                            <x-ui.detail-row :label="__('Direction')" :value="$t['sent'] ? __('Sent') : __('Received')" />
                            <x-ui.detail-row :label="$t['sent'] ? __('Recipient') : __('Sender')" class="text-right">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $t['counterparty'] }}</p>
                                @if ($t['counterpartyHandle'])
                                    <p class="truncate text-xs font-normal text-slate-400">{{ $t['counterpartyHandle'] }}</p>
                                @endif
                            </x-ui.detail-row>
                            <x-ui.detail-row :label="__('Asset')" :value="$t['symbol'] . ' · ' . $t['name']" />
                            @if ($t['kind'])
                                <x-ui.detail-row :label="__('Type')" :value="$t['kind']" />
                            @endif
                            @if ($t['memo'])
                                <x-ui.detail-row :label="__('Memo')" class="truncate text-right" :title="$t['memo']">{{ $t['memo'] }}</x-ui.detail-row>
                            @endif
                            <x-ui.detail-row :label="__('Date')" class="text-right" :value="$at->format('M j, Y · g:i A')" />
                        </x-ui.detail-list>

                        <p class="text-center font-mono text-[11px] text-slate-300">{{ $t['id'] }}</p>
                    </div>
                </x-ui.modal>
            @endforeach

            <x-ui.pagination :paginator="$transfers" />
        @elseif ($stats['total'] > 0)
            <div class="pp-card">
                <x-ui.empty-state icon="magnifying-glass" :title="__('No matching transfers')"
                    :description="__('Nothing matches your filters yet.')">
                    <x-slot:action>
                        <x-ui.button href="{{ route('send.history') }}" variant="secondary" size="sm">{{ __('Clear filters') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="paper-airplane" :title="__('No transfers yet')"
                    :description="__('Your sent and received transfers will show up here.')">
                    <x-slot:action>
                        <x-ui.button href="{{ route('send.index') }}" icon="plus" size="sm">{{ __('Send money') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @endif
    </div>
</x-layouts.app>
