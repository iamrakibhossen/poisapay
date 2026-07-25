<x-layouts.app :title="__('Earnings')">
    <div class="mt-6 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Earnings & payouts') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Your balance and withdrawals.') }}</p>
            </div>
            <x-ui.button icon="arrow-up-tray">{{ __('Request payout') }}</x-ui.button>
        </div>

        {{-- Balances --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Available', $balances['available'], 'wallet', 'text-emerald-600'],
                ['Pending', $balances['pending'], 'clock', 'text-amber-600'],
                ['Withdrawn', $balances['withdrawn'], 'arrow-up-tray', 'text-neutral-900'],
                ['Lifetime revenue', $balances['revenue'], 'banknotes', 'text-neutral-900'],
            ] as [$label, $value, $icon, $tone])
                <div class="pp-card p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ $label }}</p>
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-neutral-100 text-neutral-500"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" /></span>
                    </div>
                    <p class="tabular mt-2 text-xl font-bold tracking-tight {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        {{-- Vesting note --}}
        <x-ui.alert type="info" :title="__('How payouts work')">
            {{ __('Earnings are held for a short refund window, then move from Pending to Available. Withdraw to your bank, crypto wallet or PoisaHub wallet anytime.') }}
        </x-ui.alert>

        {{-- Payout history --}}
        <div>
            <h2 class="mb-3 px-1 text-sm font-semibold text-neutral-900">{{ __('Payout history') }}</h2>
            <x-ui.history-table :columns="[
                ['label' => __('Amount')],
                ['label' => __('Method')],
                ['label' => __('Status')],
                ['label' => __('Date'), 'align' => 'right'],
            ]">
                @foreach ($payouts as $p)
                    <tr class="transition hover:bg-neutral-50/70">
                        <td class="tabular px-5 py-4 align-middle text-sm font-semibold text-neutral-900">{{ $p['amount'] }}</td>
                        <td class="px-5 py-4 align-middle text-sm text-neutral-600">{{ $p['method'] }}</td>
                        <td class="px-5 py-4 align-middle"><x-ui.badge :color="$p['color']" dot>{{ $p['status'] }}</x-ui.badge></td>
                        <td class="whitespace-nowrap px-5 py-4 text-right align-middle text-xs text-neutral-400">{{ $p['date'] }}</td>
                    </tr>
                @endforeach
            </x-ui.history-table>
        </div>
    </div>
</x-layouts.app>
