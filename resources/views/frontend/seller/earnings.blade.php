<x-layouts.app :title="__('Earnings')">
    <div class="mt-6 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('shop') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900"><x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Shop') }}</a>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Earnings & payouts') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('What you’ve earned from sales, and how to withdraw it.') }}</p>
            </div>
            <div class="flex items-center gap-4">
                @if ($withdrawable)
                    <div class="text-right">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ __('Withdrawable now') }}</p>
                        <p class="tabular text-lg font-bold tracking-tight text-neutral-900">{{ $withdrawable }}</p>
                    </div>
                @endif
                <x-ui.button :href="$withdrawUrl" icon="arrow-up-tray">{{ __('Withdraw') }}</x-ui.button>
            </div>
        </div>

        {{-- Balances --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Available', $balances['available'], 'wallet', 'text-emerald-600', __('Cleared the refund window')],
                ['Pending', $balances['pending'], 'clock', 'text-amber-600', __('Still within the refund window')],
                ['Lifetime revenue', $balances['revenue'], 'banknotes', 'text-neutral-900', __('Net earned, after fees')],
                ['Sales', $balances['sales'], 'shopping-bag', 'text-neutral-900', __('Paid orders')],
            ] as [$label, $value, $icon, $tone, $hint])
                <div class="pp-card p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ __($label) }}</p>
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-neutral-100 text-neutral-500"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" /></span>
                    </div>
                    <p class="tabular mt-2 text-xl font-bold tracking-tight {{ $tone }}">{{ $value }}</p>
                    <p class="mt-1 text-[11px] text-neutral-400">{{ $hint }}</p>
                </div>
            @endforeach
        </div>

        {{-- How payouts work --}}
        <x-ui.alert type="info" :title="__('How payouts work')">
            {{ __('Every sale credits your net earnings (after platform fees) straight to your PoishaPay wallet. Amounts stay Pending during the buyer’s refund window, then become Available — withdraw them to your bank or crypto wallet anytime.') }}
        </x-ui.alert>

        {{-- Recent earnings --}}
        <div>
            <h2 class="mb-3 px-1 text-sm font-semibold text-neutral-900">{{ __('Recent earnings') }}</h2>

            @if (empty($recent))
                <x-ui.empty-state
                    icon="banknotes"
                    :title="__('No earnings yet')"
                    :description="__('When someone buys through one of your sales pages, your net earnings show up here.')" />
            @else
                <x-ui.history-table :columns="[
                    ['label' => __('Order')],
                    ['label' => __('Product')],
                    ['label' => __('Net earned')],
                    ['label' => __('Status')],
                    ['label' => __('Date'), 'align' => 'right'],
                ]">
                    @foreach ($recent as $r)
                        <tr class="transition hover:bg-neutral-50/70">
                            <td class="px-5 py-4 align-middle text-sm font-medium text-neutral-500">#{{ $r['number'] }}</td>
                            <td class="px-5 py-4 align-middle text-sm font-medium text-neutral-900">{{ $r['product'] }}</td>
                            <td class="tabular px-5 py-4 align-middle text-sm font-semibold text-neutral-900">{{ $r['amount'] }}</td>
                            <td class="px-5 py-4 align-middle"><x-ui.badge :color="$r['color']" dot>{{ $r['status'] }}</x-ui.badge></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right align-middle text-xs text-neutral-400">{{ $r['date'] }}</td>
                        </tr>
                    @endforeach
                </x-ui.history-table>
            @endif
        </div>

        {{-- Payouts (standard wallet withdrawals of your settlement asset) --}}
        @if (! empty($payouts))
            <div>
                <h2 class="mb-3 px-1 text-sm font-semibold text-neutral-900">{{ __('Payouts') }}</h2>
                <x-ui.history-table :columns="[
                    ['label' => __('Amount')],
                    ['label' => __('Method')],
                    ['label' => __('Status')],
                    ['label' => __('Date'), 'align' => 'right'],
                ]">
                    @foreach ($payouts as $p)
                        <tr class="transition hover:bg-neutral-50/70">
                            <td class="tabular px-5 py-4 align-middle text-sm font-semibold text-neutral-900">{{ $p['amount'] }}</td>
                            <td class="px-5 py-4 align-middle text-sm text-neutral-500">{{ $p['method'] }}</td>
                            <td class="px-5 py-4 align-middle"><x-ui.badge :color="$p['color']" dot>{{ $p['status'] }}</x-ui.badge></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right align-middle text-xs text-neutral-400">{{ $p['date'] }}</td>
                        </tr>
                    @endforeach
                </x-ui.history-table>
            </div>
        @endif
    </div>
</x-layouts.app>
