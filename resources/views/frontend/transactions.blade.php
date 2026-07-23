<x-layouts.app :title="__('Transactions')">
    <div class="space-y-5">
        <h1 class="text-xl font-semibold tracking-tight text-neutral-900">{{ __('Transactions') }}</h1>

        {{-- Stats --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @php
                $stats = [
                    ['label' => __('All-time'), 'value' => number_format($feed['total']), 'icon' => 'clock', 'fg' => 'text-neutral-900'],
                    ['label' => __('This month'), 'value' => number_format($feed['month_count']), 'icon' => 'calendar-days', 'fg' => 'text-neutral-900'],
                    ['label' => __('Received · 30d'), 'value' => $analytics['inflow'], 'icon' => 'arrow-down-left', 'fg' => 'text-emerald-600'],
                    ['label' => __('Sent · 30d'), 'value' => $analytics['outflow'], 'icon' => 'arrow-up-right', 'fg' => 'text-neutral-900'],
                ];
            @endphp
            @foreach ($stats as $s)
                <div class="pp-card flex items-center gap-3 p-4">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-500">
                        <x-dynamic-component :component="'heroicon-o-'.$s['icon']" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-[11px] font-medium uppercase tracking-wide text-neutral-500">{{ $s['label'] }}</p>
                        <p class="tabular truncate text-lg font-bold {{ $s['fg'] }}">{{ $s['value'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Toolbar: type tabs + asset filter + search — single line --}}
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="-mx-1 flex flex-nowrap gap-1 overflow-x-auto px-1 lg:flex-wrap">
                @foreach (['all' => __('All'), 'deposits' => __('Deposits'), 'withdrawals' => __('Withdrawals'), 'transfers' => __('Transfers'), 'swaps' => __('Swaps'), 'payments' => __('Payments'), 'cards' => __('Cards')] as $key => $label)
                    <a href="{{ route('transactions', array_merge(request()->query(), ['type' => $key, 'page' => 1])) }}"
                        class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium transition {{ $filters['type'] === $key ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-800' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('transactions') }}" class="flex gap-2 lg:ml-auto">
                <input type="hidden" name="type" value="{{ $filters['type'] }}" />
                <select name="asset" onchange="this.form.submit()" class="pp-input w-32 text-sm">
                    <option value="all">{{ __('All assets') }}</option>
                    @foreach ($feed['symbols'] as $symbol)
                        <option value="{{ $symbol }}" @selected($filters['asset'] === $symbol)>{{ $symbol }}</option>
                    @endforeach
                </select>
                <div class="relative flex-1 lg:w-56 lg:flex-none">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                    <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="{{ __('Search…') }}" class="pp-input w-full !pl-10 text-sm" />
                </div>
            </form>
        </div>

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
