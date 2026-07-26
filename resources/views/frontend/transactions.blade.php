<x-layouts.app :title="__('Transactions')">
    <div class="space-y-5">
        <h1 class="text-xl font-semibold tracking-tight text-neutral-900">{{ __('Transactions') }}</h1>

        <x-ui.history-stats :cards="[
            ['label' => __('All-time'), 'value' => number_format($feed['total']), 'icon' => 'clock'],
            ['label' => __('This month'), 'value' => number_format($feed['month_count']), 'icon' => 'calendar-days'],
            ['label' => __('Received · 30d'), 'value' => $analytics['inflow'], 'icon' => 'arrow-down-left', 'fg' => 'text-emerald-600'],
            ['label' => __('Sent · 30d'), 'value' => $analytics['outflow'], 'icon' => 'arrow-up-right'],
        ]" />

        <x-ui.history-filters route="transactions" tab-param="type"
            :tabs="['all' => __('All'), 'deposits' => __('Deposits'), 'withdrawals' => __('Withdrawals'), 'transfers' => __('Transfers'), 'swaps' => __('Swaps'), 'payments' => __('Payments'), 'p2p' => __('P2P'), 'cards' => __('Cards')]"
            :active="$filters['type']" :symbols="$feed['symbols']" :asset="$filters['asset']" :search="$filters['search']" />

        {{-- Activity table --}}
        @if (count($feed['items']))
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[var(--shadow-card)]">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/60 text-[11px] uppercase tracking-wider text-gray-400">
                            <th class="px-5 py-3 text-left font-semibold">{{ __('Date') }}</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ __('Transaction') }}</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ __('Status') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($feed['items'] as $item)
                            @php
                                $isDebit = str_starts_with($item['amount'], '-');
                                $at = \Illuminate\Support\Carbon::parse($item['at']);
                                $date = $at->isCurrentYear() ? $at->format('M j') : $at->format('M j, Y');
                            @endphp
                            <tr class="group cursor-pointer transition hover:bg-gray-50/70" onclick="window.location='{{ $item['url'] }}'">
                                <td class="whitespace-nowrap px-5 py-4 align-middle">
                                    <p class="text-sm font-medium text-neutral-700">{{ $date }}</p>
                                    <p class="text-xs text-neutral-400">{{ $at->format('g:i A') }}</p>
                                </td>
                                <td class="px-5 py-4 align-middle">
                                    <div class="flex items-center gap-3">
                                        <span @class([
                                            'grid h-9 w-9 shrink-0 place-items-center rounded-lg',
                                            'bg-neutral-100 text-neutral-500' => $isDebit,
                                            'bg-emerald-50 text-emerald-600' => ! $isDebit,
                                        ])>
                                            <x-dynamic-component :component="'heroicon-o-arrow-'.($isDebit ? 'up-right' : 'down-left')" class="h-4 w-4" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-neutral-900">{{ $item['title'] }}</p>
                                            <p class="truncate text-xs text-neutral-500">{{ $item['subtitle'] ?: ($item['asset'] ?? '') }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 align-middle">
                                    <x-ui.badge :color="$item['statusColor'] ?? 'gray'" dot>{{ $item['status'] }}</x-ui.badge>
                                </td>
                                <td class="tabular whitespace-nowrap px-5 py-4 text-right align-middle text-sm font-semibold {{ $isDebit ? 'text-neutral-900' : 'text-emerald-600' }}">
                                    {{ $item['amount'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="banknotes" :title="__('No transactions')" :description="__('Nothing matches your filters yet.')" />
            </div>
        @endif

        {{-- Pagination --}}
        @if ($feed['last_page'] > 1)
            <div class="flex items-center justify-between text-sm">
                @if ($feed['page'] > 1)
                    <a href="{{ route('transactions', array_merge(request()->query(), ['page' => $feed['page'] - 1])) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-gray-50">
                        <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-100 px-3 py-1.5 font-medium text-neutral-300">
                        <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
                    </span>
                @endif
                <span class="text-neutral-500">{{ __('Page :page of :last', ['page' => $feed['page'], 'last' => $feed['last_page']]) }}</span>
                @if ($feed['page'] < $feed['last_page'])
                    <a href="{{ route('transactions', array_merge(request()->query(), ['page' => $feed['page'] + 1])) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-gray-50">
                        {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-100 px-3 py-1.5 font-medium text-neutral-300">
                        {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
                    </span>
                @endif
            </div>
        @endif
    </div>
</x-layouts.app>
