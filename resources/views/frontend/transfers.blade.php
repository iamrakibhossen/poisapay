<x-layouts.app :title="'Transfer history'">
    <div class="mx-auto max-w-4xl space-y-5">
        <x-ui.page-header title="Transfer history" subtitle="Money you've sent to and received from other PoisaPay users.">
            <x-slot:actions>
                <x-ui.button href="{{ route('send') }}" icon="plus" size="sm">New transfer</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($transfers->total())
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[var(--shadow-card)]">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-neutral-50/60 text-[11px] uppercase tracking-wider text-neutral-400">
                            <th class="px-5 py-3 text-left font-semibold">Date</th>
                            <th class="px-5 py-3 text-left font-semibold">Transfer</th>
                            <th class="px-5 py-3 text-left font-semibold">Memo</th>
                            <th class="px-5 py-3 text-right font-semibold">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($transfers as $t)
                            @php
                                $at = \Illuminate\Support\Carbon::parse($t['at']);
                                $date = $at->isCurrentYear() ? $at->format('M j') : $at->format('M j, Y');
                            @endphp
                            <tr class="transition hover:bg-neutral-50/70">
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
                                            <p class="text-sm font-medium text-neutral-900">{{ $t['sent'] ? 'Sent' : 'Received' }} · {{ $t['symbol'] }}</p>
                                            <p class="truncate text-xs text-neutral-500">{{ $t['sent'] ? 'To' : 'From' }} {{ $t['counterparty'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 align-middle">
                                    <p class="truncate text-xs text-neutral-500">{{ $t['memo'] ?: '—' }}</p>
                                </td>
                                <td class="tabular whitespace-nowrap px-5 py-4 text-right align-middle text-sm font-semibold {{ $t['sent'] ? 'text-neutral-900' : 'text-emerald-600' }}">
                                    {{ $t['sent'] ? '-' : '+' }}{{ $t['amount'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($transfers->hasPages())
                <div class="flex items-center justify-between text-sm">
                    @if ($transfers->onFirstPage())
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-100 px-3 py-1.5 font-medium text-neutral-300">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> Previous
                        </span>
                    @else
                        <a href="{{ $transfers->previousPageUrl() }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> Previous
                        </a>
                    @endif
                    <span class="text-neutral-500">Page {{ $transfers->currentPage() }} of {{ $transfers->lastPage() }}</span>
                    @if ($transfers->hasMorePages())
                        <a href="{{ $transfers->nextPageUrl() }}"
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
                <x-ui.empty-state icon="paper-airplane" title="No transfers yet"
                    description="Your sent and received transfers will show up here.">
                    <x-slot:action>
                        <x-ui.button href="{{ route('send') }}" icon="plus" size="sm">Send money</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @endif
    </div>
</x-layouts.app>
