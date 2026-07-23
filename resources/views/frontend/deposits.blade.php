<x-layouts.app :title="__('Deposit history')">
    <div class="mx-auto max-w-4xl space-y-5">
        <x-ui.page-header :title="__('Deposit history')" :subtitle="__('Every deposit into your PoisaPay account.')">
            <x-slot:actions>
                <x-ui.button href="{{ route('deposit.index') }}" icon="plus" size="sm">{{ __('New deposit') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($deposits->total())
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[var(--shadow-card)]">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-neutral-50/60 text-[11px] uppercase tracking-wider text-neutral-400">
                            <th class="px-5 py-3 text-left font-semibold">{{ __('Date') }}</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ __('Deposit') }}</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ __('Source') }}</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ __('Status') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($deposits as $d)
                            @php
                                $at = \Illuminate\Support\Carbon::parse($d['at']);
                                $date = $at->isCurrentYear() ? $at->format('M j') : $at->format('M j, Y');
                            @endphp
                            <tr class="transition hover:bg-neutral-50/70">
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
                                        @if ($d['explorer'])
                                            <a href="{{ $d['explorer'] }}" target="_blank" rel="noopener"
                                                class="mt-0.5 inline-flex items-center gap-1 font-mono text-xs text-brand-600 transition hover:text-brand-700"
                                                title="{{ $d['txid'] }}">
                                                {{ $d['txidShort'] }}
                                                <x-heroicon-o-arrow-top-right-on-square class="h-3 w-3" />
                                            </a>
                                        @else
                                            <p class="mt-0.5 font-mono text-xs text-neutral-400" title="{{ $d['txid'] }}">{{ $d['txidShort'] }}</p>
                                        @endif
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($deposits->hasPages())
                <div class="flex items-center justify-between text-sm">
                    @if ($deposits->onFirstPage())
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-100 px-3 py-1.5 font-medium text-neutral-300">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
                        </span>
                    @else
                        <a href="{{ $deposits->previousPageUrl() }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
                        </a>
                    @endif
                    <span class="text-neutral-500">{{ __('Page :current of :last', ['current' => $deposits->currentPage(), 'last' => $deposits->lastPage()]) }}</span>
                    @if ($deposits->hasMorePages())
                        <a href="{{ $deposits->nextPageUrl() }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50">
                            {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-100 px-3 py-1.5 font-medium text-neutral-300">
                            {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </span>
                    @endif
                </div>
            @endif
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
