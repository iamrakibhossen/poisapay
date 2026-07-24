<x-layouts.app :title="__('Swap history')">
    <div class="space-y-5">
        <x-ui.page-header :title="__('Swap history')" :subtitle="__('Every coin-to-coin exchange on your account.')">
            <x-slot:actions>
                <x-ui.button href="{{ route('exchange.index') }}" icon="plus" size="sm">{{ __('New swap') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.history-stats :cards="[
            ['label' => __('All-time'), 'value' => number_format($stats['total']), 'icon' => 'clock'],
            ['label' => __('This month'), 'value' => number_format($stats['month']), 'icon' => 'calendar-days'],
            ['label' => __('Volume · all-time'), 'value' => $stats['volume'], 'icon' => 'arrows-right-left'],
            ['label' => __('Volume · 30d'), 'value' => $stats['volume30d'], 'icon' => 'chart-bar'],
        ]" />

        <x-ui.history-filters route="exchange.history" tab-param="context"
            :tabs="['all' => __('All'), 'swap' => __('Swap'), 'ramp' => __('On-ramp'), 'card_settle' => __('Card')]"
            :active="$filters['context']" :symbols="$symbols" :asset="$filters['asset']" :search="$filters['search']"
            :search-placeholder="__('Coin symbol…')" />

        @if ($swaps->total())
            <x-ui.history-table :columns="[
                ['label' => __('Date')],
                ['label' => __('Swap')],
                ['label' => __('Paid'), 'align' => 'right'],
                ['label' => __('Received'), 'align' => 'right'],
                ['label' => __('Details'), 'align' => 'right', 'sr' => true],
            ]">
                @foreach ($swaps as $s)
                    @php
                        $at = \Illuminate\Support\Carbon::parse($s['at']);
                        $date = $at->isCurrentYear() ? $at->format('M j') : $at->format('M j, Y');
                    @endphp
                    <tr class="cursor-pointer transition hover:bg-neutral-50/70"
                        role="button" tabindex="0"
                        x-on:click="$dispatch('open-modal', 'swap-{{ $s['id'] }}')"
                        x-on:keydown.enter="$dispatch('open-modal', 'swap-{{ $s['id'] }}')"
                        x-on:keydown.space.prevent="$dispatch('open-modal', 'swap-{{ $s['id'] }}')">
                        <td class="whitespace-nowrap px-5 py-4 align-middle">
                            <p class="text-sm font-medium text-neutral-700">{{ $date }}</p>
                            <p class="text-xs text-neutral-400">{{ $at->format('g:i A') }}</p>
                        </td>
                        <td class="px-5 py-4 align-middle">
                            <div class="flex items-center gap-2">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-50 text-[10px] font-bold text-brand-600">{{ \Illuminate\Support\Str::substr($s['fromSymbol'], 0, 2) }}</span>
                                <x-heroicon-o-arrow-right class="h-4 w-4 shrink-0 text-neutral-400" />
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-neutral-800 text-[10px] font-bold text-white">{{ \Illuminate\Support\Str::substr($s['toSymbol'], 0, 2) }}</span>
                                <span class="ml-1 text-sm font-medium text-neutral-900">{{ $s['fromSymbol'] }} → {{ $s['toSymbol'] }}</span>
                            </div>
                        </td>
                        <td class="tabular whitespace-nowrap px-5 py-4 text-right align-middle text-sm text-neutral-600">-{{ $s['fromAmount'] }}</td>
                        <td class="tabular whitespace-nowrap px-5 py-4 text-right align-middle text-sm font-semibold text-emerald-600">+{{ $s['toAmount'] }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                            <x-heroicon-o-chevron-right class="ms-auto h-4 w-4 text-neutral-300" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.history-table>

            {{-- Per-swap detail modals — the entire record for each swap. --}}
            @foreach ($swaps as $s)
                @php
                    $at = \Illuminate\Support\Carbon::parse($s['at']);
                    $completedAt = $s['completedAt'] ? \Illuminate\Support\Carbon::parse($s['completedAt']) : null;
                @endphp
                <x-ui.modal name="swap-{{ $s['id'] }}" :title="__('Swap details')" :subtitle="$s['fromSymbol'] . ' → ' . $s['toSymbol']" maxWidth="lg">
                    <div class="space-y-6">
                        {{-- Headline: from → to --}}
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-600">{{ \Illuminate\Support\Str::substr($s['fromSymbol'], 0, 2) }}</span>
                                <x-heroicon-o-arrow-right class="h-5 w-5 shrink-0 text-neutral-400" />
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-neutral-800 text-xs font-bold text-white">{{ \Illuminate\Support\Str::substr($s['toSymbol'], 0, 2) }}</span>
                            </div>
                            @if ($s['context'])
                                <x-ui.badge color="gray">{{ $s['context'] }}</x-ui.badge>
                            @endif
                        </div>

                        {{-- Amounts --}}
                        <x-ui.detail-list>
                            <x-ui.detail-row :label="__('You paid')" class="tabular text-neutral-700">-{{ $s['fromAmount'] }}</x-ui.detail-row>
                            <x-ui.detail-row :label="__('You received')" class="tabular font-semibold text-emerald-600">+{{ $s['toAmount'] }}</x-ui.detail-row>
                            <x-ui.detail-row :label="__('Rate')" class="tabular">1 {{ $s['fromSymbol'] }} = {{ $s['rate'] }} {{ $s['toSymbol'] }}</x-ui.detail-row>
                            @if ($s['marketRate'])
                                <x-ui.detail-row :label="__('Market rate')" class="tabular">1 {{ $s['fromSymbol'] }} = {{ $s['marketRate'] }} {{ $s['toSymbol'] }}</x-ui.detail-row>
                            @endif
                        </x-ui.detail-list>

                        {{-- Costs --}}
                        @if ($s['spread'] || $s['fee'] || $s['spreadBps'] || $s['feeBps'])
                            <x-ui.detail-list :heading="__('Costs')">
                                @if ($s['spread'] || $s['spreadBps'])
                                    <x-ui.detail-row :label="__('Spread')" class="tabular">
                                        {{ $s['spread'] ?? '—' }}<span class="text-slate-400"> · {{ number_format($s['spreadBps'] / 100, 2) }}%</span>
                                    </x-ui.detail-row>
                                @endif
                                @if ($s['fee'] || $s['feeBps'])
                                    <x-ui.detail-row :label="__('Fee')" class="tabular">
                                        {{ $s['fee'] ?? '—' }}<span class="text-slate-400"> · {{ number_format($s['feeBps'] / 100, 2) }}%</span>
                                    </x-ui.detail-row>
                                @endif
                            </x-ui.detail-list>
                        @endif

                        {{-- Meta --}}
                        <x-ui.detail-list>
                            <x-ui.detail-row :label="__('From')" :value="$s['fromSymbol'] . ' · ' . $s['fromName']" />
                            <x-ui.detail-row :label="__('To')" :value="$s['toSymbol'] . ' · ' . $s['toName']" />
                            @if ($s['status'])
                                <x-ui.detail-row :label="__('Status')" :value="$s['status']" />
                            @endif
                            <x-ui.detail-row :label="__('Date')" class="text-right" :value="$at->format('M j, Y · g:i A')" />
                            @if ($completedAt)
                                <x-ui.detail-row :label="__('Completed')" class="text-right" :value="$completedAt->format('M j, Y · g:i A')" />
                            @endif
                        </x-ui.detail-list>

                        <p class="text-center font-mono text-[11px] text-slate-300">{{ $s['id'] }}</p>
                    </div>
                </x-ui.modal>
            @endforeach

            <x-ui.pagination :paginator="$swaps" />
        @elseif ($stats['total'] > 0)
            <div class="pp-card">
                <x-ui.empty-state icon="magnifying-glass" :title="__('No matching swaps')"
                    :description="__('Nothing matches your filters yet.')">
                    <x-slot:action>
                        <x-ui.button href="{{ route('exchange.history') }}" variant="secondary" size="sm">{{ __('Clear filters') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="arrows-right-left" :title="__('No swaps yet')"
                    :description="__('Your completed swaps will appear here.')">
                    <x-slot:action>
                        <x-ui.button href="{{ route('exchange.index') }}" icon="plus" size="sm">{{ __('Make a swap') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @endif
    </div>
</x-layouts.app>
