<x-layouts.app :title="__('My P2P orders')">
    @php
        $tabs = [
            'all' => __('All'),
            'active' => __('Active'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            'disputed' => __('Disputed'),
        ];
        $hasFilters = request()->hasAny(['role', 'search', 'from', 'to']) || $tab !== 'all';
    @endphp

    <div class="space-y-5">
        {{-- Header --}}
        <x-ui.page-header :title="__('My orders')" :subtitle="__('Track and manage your P2P trades.')">
            <x-slot:actions>
                <a href="{{ route('p2p') }}"><x-ui.button variant="secondary" icon="arrow-left">{{ __('Marketplace') }}</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Filter toolbar — same pattern as the transactions page: pill tabs + right-aligned form --}}
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="-mx-1 flex flex-nowrap gap-1 overflow-x-auto px-1 lg:flex-wrap">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('p2p.orders', array_merge(request()->except(['tab', 'page']), $key === 'all' ? [] : ['tab' => $key])) }}"
                       class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium transition {{ $tab === $key ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-800' }}">
                        {{ $label }} <span class="tabular opacity-70">{{ number_format($counts[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('p2p.orders') }}" class="flex gap-2 lg:ml-auto">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <select name="role" onchange="this.form.submit()" class="pp-input w-32 text-sm">
                    <option value="">{{ __('All sides') }}</option>
                    <option value="buying" @selected(request('role') === 'buying')>{{ __('Buying') }}</option>
                    <option value="selling" @selected(request('role') === 'selling')>{{ __('Selling') }}</option>
                </select>
                <div class="relative flex-1 lg:w-56 lg:flex-none">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('Search order number…') }}" class="pp-input w-full !pl-10 text-sm" />
                </div>
            </form>
        </div>

        {{-- Orders --}}
        @if ($orders->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="shopping-bag"
                    :title="$hasFilters ? __('No matching orders') : __('No orders yet')"
                    :description="$hasFilters ? __('Try a different tab or clear your filters.') : __('Browse the marketplace to place your first order.')">
                    <x-slot:action>
                        @if ($hasFilters)
                            <a href="{{ route('p2p.orders') }}"><x-ui.button variant="secondary">{{ __('Clear filters') }}</x-ui.button></a>
                        @else
                            <a href="{{ route('p2p') }}"><x-ui.button icon="arrow-right">{{ __('Explore marketplace') }}</x-ui.button></a>
                        @endif
                    </x-slot:action>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            {{-- Column header (desktop) --}}
            <div class="hidden px-4 lg:grid lg:grid-cols-[1.1fr_1.3fr_1fr_1.2fr_1fr_auto] lg:gap-4 lg:text-xs lg:font-semibold lg:uppercase lg:tracking-wider lg:text-neutral-400">
                <span>{{ __('Side / Date') }}</span>
                <span>{{ __('Order number') }}</span>
                <span>{{ __('Price') }}</span>
                <span>{{ __('Amount') }}</span>
                <span>{{ __('Counterparty') }}</span>
                <span class="text-right">{{ __('Status') }}</span>
            </div>

            <div class="space-y-3 lg:space-y-2">
                @foreach ($orders as $order)
                    @php
                        $isBuyer = $order->buyer_id === $me;
                        $status = $order->status->value;
                        $actionNeeded = ($isBuyer && $status === 'waiting_payment') || (! $isBuyer && $status === 'buyer_paid');
                        $counterparty = ($isBuyer ? $order->seller?->name : $order->buyer?->name) ?? '—';
                    @endphp
                    <div class="relative grid grid-cols-1 gap-3 rounded-xl border bg-white p-4 shadow-[var(--shadow-card)] transition-colors hover:border-neutral-300 lg:grid-cols-[1.1fr_1.3fr_1fr_1.2fr_1fr_auto] lg:items-center lg:gap-4 {{ $actionNeeded ? 'border-amber-300 ring-1 ring-amber-100' : 'border-neutral-200' }}">

                        {{-- Side / Date --}}
                        <div class="flex items-center justify-between lg:block">
                            <div>
                                <p class="flex items-center gap-1.5 text-sm font-semibold">
                                    <span class="inline-flex h-5 items-center rounded px-1.5 text-xs font-bold {{ $isBuyer ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $isBuyer ? __('BUY') : __('SELL') }}</span>
                                    <span class="text-neutral-900">{{ $order->asset->symbol ?? 'USDT' }}</span>
                                </p>
                                <p class="mt-1 text-xs text-neutral-400">{{ $order->created_at?->format('d M, Y · h:i A') }}</p>
                            </div>
                            @if ($actionNeeded)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 lg:hidden">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>{{ __('Action needed') }}
                                </span>
                            @endif
                        </div>

                        {{-- Order number --}}
                        <div class="flex items-center justify-between lg:block">
                            <span class="text-xs text-neutral-400 lg:hidden">{{ __('Order') }}</span>
                            <div class="flex items-center gap-1">
                                <a href="{{ route('p2p.order', $order) }}" class="font-mono text-xs font-medium text-neutral-700 hover:text-brand-600 hover:underline">{{ $order->ref }}</a>
                                <x-ui.copy-text :text="$order->ref" />
                            </div>
                        </div>

                        {{-- Price --}}
                        <div class="flex items-center justify-between lg:block">
                            <span class="text-xs text-neutral-400 lg:hidden">{{ __('Price') }}</span>
                            <span class="text-sm font-semibold tabular text-neutral-900">{{ number_format((float) $order->price, 2) }} <span class="text-xs font-medium text-neutral-400">{{ $order->fiat_currency }}</span></span>
                        </div>

                        {{-- Amount --}}
                        <div class="flex items-center justify-between lg:block">
                            <span class="text-xs text-neutral-400 lg:hidden">{{ __('Amount') }}</span>
                            <div class="text-right lg:text-left">
                                <p class="text-sm tabular text-neutral-700">{{ $order->cryptoMoney()->format() }}</p>
                                <p class="text-xs tabular text-neutral-400">{{ number_format((float) $order->fiat_amount, 2) }} {{ $order->fiat_currency }}</p>
                            </div>
                        </div>

                        {{-- Counterparty --}}
                        <div class="flex items-center justify-between lg:block">
                            <span class="text-xs text-neutral-400 lg:hidden">{{ __('Counterparty') }}</span>
                            <div class="flex items-center gap-2">
                                <x-ui.avatar :name="$counterparty === '—' ? '?' : $counterparty" size="sm" class="hidden lg:inline-grid" />
                                <span class="truncate text-sm text-neutral-700">{{ $counterparty }}</span>
                            </div>
                        </div>

                        {{-- Status / Action --}}
                        <div class="flex items-center justify-between gap-3 border-t border-neutral-100 pt-3 lg:justify-end lg:border-0 lg:pt-0">
                            <x-ui.badge :color="$order->status->color()" dot>{{ $order->status->label() }}</x-ui.badge>
                            <a href="{{ route('p2p.order', $order) }}">
                                <x-ui.button size="sm" :variant="$actionNeeded ? 'primary' : 'secondary'" icon="arrow-right">
                                    {{ $actionNeeded ? ($isBuyer ? __('Pay now') : __('Release')) : __('View') }}
                                </x-ui.button>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination — prev / next (matches the transactions page) --}}
            @if ($orders->lastPage() > 1)
                <div class="flex items-center justify-between text-sm">
                    @if (! $orders->onFirstPage())
                        <a href="{{ $orders->previousPageUrl() }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-gray-50">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-100 px-3 py-1.5 font-medium text-neutral-300">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
                        </span>
                    @endif
                    <span class="text-neutral-500">{{ __('Page :page of :last', ['page' => $orders->currentPage(), 'last' => $orders->lastPage()]) }}</span>
                    @if ($orders->hasMorePages())
                        <a href="{{ $orders->nextPageUrl() }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-gray-50">
                            {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-100 px-3 py-1.5 font-medium text-neutral-300">
                            {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </span>
                    @endif
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
