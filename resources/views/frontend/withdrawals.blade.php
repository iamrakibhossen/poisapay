<x-layouts.app :title="'Withdrawal history'">
    <div class="mx-auto max-w-4xl space-y-5">
        <x-ui.page-header title="Withdrawal history" subtitle="Every withdrawal and cash-out from your PoisaPay account.">
            <x-slot:actions>
                <x-ui.button href="{{ route('withdraw') }}" icon="plus" size="sm">New withdrawal</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($withdrawals->total())
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[var(--shadow-card)]">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-neutral-50/60 text-[11px] uppercase tracking-wider text-neutral-400">
                            <th class="px-5 py-3 text-left font-semibold">Date</th>
                            <th class="px-5 py-3 text-left font-semibold">Withdrawal</th>
                            <th class="px-5 py-3 text-left font-semibold">Destination</th>
                            <th class="px-5 py-3 text-left font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($withdrawals as $w)
                            @php
                                $at = \Illuminate\Support\Carbon::parse($w['at']);
                                $date = $at->isCurrentYear() ? $at->format('M j') : $at->format('M j, Y');
                            @endphp
                            <tr class="transition hover:bg-neutral-50/70">
                                <td class="whitespace-nowrap px-5 py-4 align-middle">
                                    <p class="text-sm font-medium text-neutral-700">{{ $date }}</p>
                                    <p class="text-xs text-neutral-400">{{ $at->format('g:i A') }}</p>
                                </td>
                                <td class="px-5 py-4 align-middle">
                                    <div class="flex items-center gap-3">
                                        <x-ui.asset-icon :symbol="$w['symbol']" size="md" />
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-neutral-900">{{ $w['symbol'] }}</p>
                                            <p class="truncate text-xs text-neutral-500">{{ $w['network'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 align-middle">
                                    <p class="truncate font-mono text-xs text-neutral-500">{{ $w['to'] ?? '—' }}</p>
                                    @if ($w['txidShort'])
                                        @if ($w['explorer'])
                                            <a href="{{ $w['explorer'] }}" target="_blank" rel="noopener"
                                                class="mt-0.5 inline-flex items-center gap-1 font-mono text-xs text-brand-600 transition hover:text-brand-700"
                                                title="{{ $w['txid'] }}">
                                                {{ $w['txidShort'] }}
                                                <x-heroicon-o-arrow-top-right-on-square class="h-3 w-3" />
                                            </a>
                                        @else
                                            <p class="mt-0.5 font-mono text-xs text-neutral-400" title="{{ $w['txid'] }}">{{ $w['txidShort'] }}</p>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-5 py-4 align-middle">
                                    <x-ui.badge :color="$w['statusColor'] ?? 'gray'" dot>{{ $w['status'] }}</x-ui.badge>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                                    <p class="tabular text-sm font-semibold text-neutral-900">-{{ $w['amount'] }}</p>
                                    @if ($w['fee'])
                                        <p class="tabular mt-0.5 text-[11px] text-neutral-400">fee {{ $w['fee'] }}</p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($withdrawals->hasPages())
                <div class="flex items-center justify-between text-sm">
                    @if ($withdrawals->onFirstPage())
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-100 px-3 py-1.5 font-medium text-neutral-300">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> Previous
                        </span>
                    @else
                        <a href="{{ $withdrawals->previousPageUrl() }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> Previous
                        </a>
                    @endif
                    <span class="text-neutral-500">Page {{ $withdrawals->currentPage() }} of {{ $withdrawals->lastPage() }}</span>
                    @if ($withdrawals->hasMorePages())
                        <a href="{{ $withdrawals->nextPageUrl() }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50">
                            Next <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-100 px-3 py-1.5 font-medium text-neutral-300">
                            Next <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </span>
                    @endif
                </div>
            @endif
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="arrow-up-tray" title="No withdrawals yet"
                    description="Your withdrawal requests will appear here.">
                    <x-slot:action>
                        <x-ui.button href="{{ route('withdraw') }}" icon="plus" size="sm">Make a withdrawal</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @endif
    </div>
</x-layouts.app>
