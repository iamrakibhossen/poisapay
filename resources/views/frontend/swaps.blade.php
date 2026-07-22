<x-layouts.app :title="'Swap history'">
    <div class="mx-auto max-w-4xl space-y-5">
        <x-ui.page-header title="Swap history" subtitle="Every coin-to-coin exchange on your account.">
            <x-slot:actions>
                <x-ui.button href="{{ route('exchange') }}" icon="plus" size="sm">New swap</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($swaps->total())
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[var(--shadow-card)]">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-neutral-50/60 text-[11px] uppercase tracking-wider text-neutral-400">
                            <th class="px-5 py-3 text-left font-semibold">Date</th>
                            <th class="px-5 py-3 text-left font-semibold">Swap</th>
                            <th class="px-5 py-3 text-right font-semibold">Paid</th>
                            <th class="px-5 py-3 text-right font-semibold">Received</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($swaps as $s)
                            @php
                                $at = \Illuminate\Support\Carbon::parse($s['at']);
                                $date = $at->isCurrentYear() ? $at->format('M j') : $at->format('M j, Y');
                            @endphp
                            <tr class="transition hover:bg-neutral-50/70">
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($swaps->hasPages())
                <div class="flex items-center justify-between text-sm">
                    @if ($swaps->onFirstPage())
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-100 px-3 py-1.5 font-medium text-neutral-300">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> Previous
                        </span>
                    @else
                        <a href="{{ $swaps->previousPageUrl() }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> Previous
                        </a>
                    @endif
                    <span class="text-neutral-500">Page {{ $swaps->currentPage() }} of {{ $swaps->lastPage() }}</span>
                    @if ($swaps->hasMorePages())
                        <a href="{{ $swaps->nextPageUrl() }}"
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
                <x-ui.empty-state icon="arrows-right-left" title="No swaps yet"
                    description="Your completed swaps will appear here.">
                    <x-slot:action>
                        <x-ui.button href="{{ route('exchange') }}" icon="plus" size="sm">Make a swap</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @endif
    </div>
</x-layouts.app>
