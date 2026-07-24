<x-layouts.app :title="__('Asset')">
    @php
        // Quick actions adapt to the rails this coin actually supports.
        $actions = [];
        if ($canDeposit) {
            $actions[] = ['route' => route('deposit.index', ['asset' => $asset['id']]), 'label' => __('Deposit'), 'icon' => 'arrow-down-tray'];
        }
        $actions[] = ['route' => route('send.index'), 'label' => __('Send'), 'icon' => 'paper-airplane'];
        $actions[] = ['route' => route('exchange.index'), 'label' => __('Swap'), 'icon' => 'arrows-right-left'];
        if ($canWithdraw) {
            $actions[] = ['route' => $asset['is_fiat'] ? route('withdraw.index', ['cash' => $asset['id']]) : route('withdraw.index'), 'label' => __('Withdraw'), 'icon' => 'arrow-up-tray'];
        }
        $cols = count($actions);
    @endphp

    <div class="mx-auto max-w-3xl space-y-5">
        <a href="{{ route('wallet') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900">
            <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Wallet') }}
        </a>

        {{-- Balance hero (light-primary, matches the wallet total-balance box) --}}
        <div class="pp-card relative overflow-hidden border-brand-100 bg-gradient-to-br from-white to-brand-50 p-6 sm:p-7">
            <div class="absolute -right-8 -top-10 h-40 w-40 rounded-full bg-brand-300/20 blur-3xl"></div>
            <div class="absolute -bottom-12 -left-6 h-32 w-32 rounded-full bg-brand-200/25 blur-2xl"></div>

            <div class="relative">
                {{-- Identity --}}
                <div class="flex items-center gap-4">
                    <x-ui.asset-icon :symbol="$asset['symbol']" size="lg" class="h-12 w-12 text-sm ring-4 ring-white/70" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-lg font-semibold text-neutral-900">{{ $asset['symbol'] }}</h1>
                            @if ($asset['chain'])
                                <x-ui.badge :color="$asset['chain']['color']">{{ $asset['chain']['name'] }}</x-ui.badge>
                            @elseif (! empty($networks))
                                <x-ui.badge color="gray">{{ count($networks) }} {{ __('networks') }}</x-ui.badge>
                            @endif
                            @if ($asset['is_stablecoin'])
                                <x-ui.badge color="success">{{ __('Stablecoin') }}</x-ui.badge>
                            @endif
                        </div>
                        <p class="truncate text-sm text-neutral-500">{{ $asset['name'] }}</p>
                    </div>
                </div>

                {{-- Balance --}}
                <div class="mt-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-brand-700">{{ __('Available balance') }}</p>
                    <p class="tabular mt-1.5 text-4xl font-bold tracking-tight text-neutral-900">{{ $balance['available'] }}</p>
                    @if ($fiat)
                        <p class="mt-1 text-sm text-neutral-500">≈ {{ $fiat }}</p>
                    @endif
                </div>

                {{-- Quick actions --}}
                <div class="mt-6 grid gap-2 sm:mt-7 sm:max-w-md" style="grid-template-columns: repeat({{ $cols }}, minmax(0, 1fr));">
                    @foreach ($actions as $a)
                        <a href="{{ $a['route'] }}" class="group flex flex-col items-center gap-1.5">
                            <span class="grid h-11 w-11 place-items-center rounded-full bg-white text-brand-600 shadow-sm ring-1 ring-brand-100 transition group-hover:bg-brand-500 group-hover:text-white group-hover:ring-brand-500">
                                <x-dynamic-component :component="'heroicon-o-'.$a['icon']" class="h-5 w-5" />
                            </span>
                            <span class="text-[11px] font-medium text-neutral-600 group-hover:text-neutral-900">{{ $a['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Available / Locked / Total breakdown --}}
        <div class="grid grid-cols-3 gap-3">
            @php
                $breakdown = [
                    ['label' => __('Available'), 'value' => $balance['available'], 'tone' => 'text-neutral-900'],
                    ['label' => __('Locked'), 'value' => $balance['locked'], 'tone' => $balance['locked'] === $balance['total'] ? 'text-neutral-400' : 'text-amber-600'],
                    ['label' => __('Total'), 'value' => $balance['total'], 'tone' => 'text-neutral-900'],
                ];
            @endphp
            @foreach ($breakdown as $b)
                <div class="pp-card p-4">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ $b['label'] }}</p>
                    <p class="tabular mt-1.5 truncate text-lg font-bold tracking-tight {{ $b['tone'] }}">{{ $b['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Settlement networks (multi-chain coins) --}}
        @if (count($networks) > 1)
            <div class="pp-card flex flex-wrap items-center gap-2 p-4">
                <span class="text-xs font-medium text-neutral-500">{{ __('Settles on') }}</span>
                @foreach ($networks as $n)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-400"></span>{{ $n['chain'] }}
                    </span>
                @endforeach
            </div>
        @endif

        {{-- Activity — clickable rows open a full detail modal, like the deposit history page. --}}
        <div>
            <h2 class="mb-3 px-1 text-sm font-semibold text-neutral-900">{{ __('Activity') }}</h2>
            @if (count($transactions))
                <x-ui.history-table :columns="[
                    ['label' => __('Date')],
                    ['label' => __('Transaction')],
                    ['label' => __('Status')],
                    ['label' => __('Amount'), 'align' => 'right'],
                    ['label' => __('Details'), 'align' => 'right', 'sr' => true],
                ]">
                    @foreach ($transactions as $item)
                        @php
                            $at = \Illuminate\Support\Carbon::parse($item['at']);
                            $date = $at->isCurrentYear() ? $at->format('M j') : $at->format('M j, Y');
                        @endphp
                        <tr class="cursor-pointer transition hover:bg-neutral-50/70"
                            role="button" tabindex="0"
                            x-on:click="$dispatch('open-modal', 'activity-{{ $item['id'] }}')"
                            x-on:keydown.enter="$dispatch('open-modal', 'activity-{{ $item['id'] }}')"
                            x-on:keydown.space.prevent="$dispatch('open-modal', 'activity-{{ $item['id'] }}')">
                            <td class="whitespace-nowrap px-5 py-4 align-middle">
                                <p class="text-sm font-medium text-neutral-700">{{ $date }}</p>
                                <p class="text-xs text-neutral-400">{{ $at->format('g:i A') }}</p>
                            </td>
                            <td class="px-5 py-4 align-middle">
                                <div class="flex items-center gap-3">
                                    <span @class([
                                        'grid h-9 w-9 shrink-0 place-items-center rounded-lg',
                                        'bg-emerald-50 text-emerald-600' => $item['isCredit'],
                                        'bg-neutral-100 text-neutral-500' => ! $item['isCredit'],
                                    ])>
                                        <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-neutral-900">{{ $item['title'] }}</p>
                                        @if ($item['subtitle'])
                                            <p class="truncate text-xs text-neutral-500">{{ $item['subtitle'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 align-middle">
                                <x-ui.badge :color="$item['statusColor'] ?? 'gray'" dot>{{ $item['status'] }}</x-ui.badge>
                            </td>
                            <td class="tabular whitespace-nowrap px-5 py-4 text-right align-middle text-sm font-semibold {{ $item['isCredit'] ? 'text-emerald-600' : 'text-neutral-900' }}">{{ $item['amount'] }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                                <x-heroicon-o-chevron-right class="ms-auto h-4 w-4 text-neutral-300" />
                            </td>
                        </tr>
                    @endforeach
                </x-ui.history-table>

                {{-- Per-item detail modals — the full record for each activity entry. --}}
                @foreach ($transactions as $item)
                    <x-ui.modal name="activity-{{ $item['id'] }}" :title="__('Transaction details')" :subtitle="$item['title']" maxWidth="lg">
                        <div class="space-y-6">
                            {{-- Headline: amount + status --}}
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span @class([
                                        'grid h-11 w-11 shrink-0 place-items-center rounded-xl',
                                        'bg-emerald-50 text-emerald-600' => $item['isCredit'],
                                        'bg-neutral-100 text-neutral-500' => ! $item['isCredit'],
                                    ])>
                                        <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <p class="tabular text-xl font-semibold {{ $item['isCredit'] ? 'text-emerald-600' : 'text-neutral-900' }}">{{ $item['amount'] }}</p>
                                        <p class="text-xs text-slate-400">{{ $item['subtitle'] ?: $item['title'] }}</p>
                                    </div>
                                </div>
                                <x-ui.badge :color="$item['statusColor'] ?? 'gray'" dot>{{ $item['status'] }}</x-ui.badge>
                            </div>

                            {{-- Detail rows --}}
                            <x-ui.detail-list>
                                @foreach ($item['rows'] as $row)
                                    <x-ui.detail-row :label="$row['label']" :class="($row['mono'] ?? false) ? 'tabular' : ''">
                                        @if (! empty($row['copy']) || ! empty($row['explorer']))
                                            <div class="flex min-w-0 items-center justify-end gap-2">
                                                <span class="truncate {{ ($row['mono'] ?? false) ? 'font-mono text-xs' : '' }} text-slate-700" title="{{ $row['copy'] ?? $row['value'] }}">{{ $row['value'] }}</span>
                                                @if (! empty($row['copy']))
                                                    <x-ui.copy-text :text="$row['copy']" />
                                                @endif
                                                @if (! empty($row['explorer']))
                                                    <a href="{{ $row['explorer'] }}" target="_blank" rel="noopener" class="text-brand-600 transition hover:text-brand-700" title="{{ __('View on explorer') }}">
                                                        <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                                                    </a>
                                                @endif
                                            </div>
                                        @else
                                            {{ $row['value'] }}
                                        @endif
                                    </x-ui.detail-row>
                                @endforeach
                            </x-ui.detail-list>

                            <p class="text-center font-mono text-[11px] text-slate-300">{{ $item['id'] }}</p>
                        </div>
                    </x-ui.modal>
                @endforeach
            @else
                <div class="pp-card">
                    <x-ui.empty-state icon="clock" :title="__('No activity')"
                        :description="__('Deposits, withdrawals and transfers for this asset will appear here.')" />
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
