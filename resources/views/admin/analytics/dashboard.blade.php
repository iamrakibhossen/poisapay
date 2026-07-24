<x-layouts.admin :title="$title">
    <div class="space-y-6">
        <x-analytics.filter-bar
            :title="$title"
            :section="$section"
            :period="$period"
            :presets="$presets"
            :filters="$filters"
            :nav="$nav"
            :compare="$compare"
        />

        {{-- Alerts --}}
        @if (! empty($report['alerts']))
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                @php
                    $styles = [
                        'critical' => ['bg-rose-50 border-rose-500 text-rose-800', 'exclamation-triangle', 'bg-rose-100 text-rose-600'],
                        'warn' => ['bg-amber-50 border-amber-500 text-amber-800', 'exclamation-circle', 'bg-amber-100 text-amber-600'],
                        'info' => ['bg-sky-50 border-sky-500 text-sky-800', 'information-circle', 'bg-sky-100 text-sky-600'],
                    ];
                    $order = ['critical' => 0, 'warn' => 1, 'info' => 2];
                    $alerts = collect($report['alerts'])->sortBy(fn ($a) => $order[$a['level']] ?? 3);
                @endphp
                @foreach ($alerts as $alert)
                    @php [$cls, $icon, $iconCls] = $styles[$alert['level']] ?? $styles['info']; @endphp
                    <div class="flex items-start gap-3 rounded-[var(--radius-card)] border-l-4 p-4 {{ $cls }}">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg {{ $iconCls }}">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ $alert['title'] }}</p>
                            <p class="text-xs opacity-90">{{ $alert['message'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- KPI wall --}}
        @if (! empty($report['kpis']))
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($report['kpis'] as $kpi)
                    <x-analytics.kpi :kpi="$kpi" :compare="$compare" />
                @endforeach
            </div>
        @endif

        {{-- Charts --}}
        @if (! empty($report['charts']))
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                @foreach ($report['charts'] as $chart)
                    <x-analytics.chart :chart="$chart" />
                @endforeach
            </div>
        @endif

        {{-- Tables --}}
        @foreach ($report['tables'] as $table)
            <div class="pp-card overflow-hidden">
                <div class="border-b border-neutral-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">{{ $table['title'] }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-neutral-100 bg-neutral-50/60 text-xs uppercase tracking-wide text-neutral-400">
                                @foreach ($table['headers'] as $i => $header)
                                    <th class="px-5 py-3 font-semibold {{ ($table['align'][$i] ?? 'left') === 'right' ? 'text-right' : 'text-left' }}">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($table['rows'] as $row)
                                <tr class="border-b border-neutral-50 last:border-0 hover:bg-neutral-50/60">
                                    @foreach ($row as $i => $cell)
                                        <td class="px-5 py-3 {{ ($table['align'][$i] ?? 'left') === 'right' ? 'tabular text-right font-medium text-neutral-900' : 'text-neutral-700' }}">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($table['headers']) }}" class="px-5 py-8 text-center text-sm text-neutral-400">{{ __('No data for this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        {{-- Honest empty-state notes --}}
        @if (! empty($report['notes']))
            <div class="rounded-[var(--radius-card)] border border-dashed border-neutral-200 bg-neutral-50/50 p-4">
                <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400">
                    <x-heroicon-o-information-circle class="h-4 w-4" /> {{ __('Data coverage notes') }}
                </p>
                <ul class="space-y-1">
                    @foreach ($report['notes'] as $note)
                        <li class="flex items-start gap-2 text-xs text-neutral-500">
                            <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-neutral-300"></span>
                            {{ $note }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Keyboard shortcuts hint --}}
        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 pt-1 text-[11px] text-neutral-400 print:hidden">
            <span class="font-semibold uppercase tracking-wide">{{ __('Shortcuts') }}</span>
            <span><kbd class="rounded border border-neutral-300 bg-white px-1 font-mono">1</kbd>–<kbd class="rounded border border-neutral-300 bg-white px-1 font-mono">0</kbd> {{ __('period') }}</span>
            <span><kbd class="rounded border border-neutral-300 bg-white px-1 font-mono">c</kbd> {{ __('compare') }}</span>
            <span><kbd class="rounded border border-neutral-300 bg-white px-1 font-mono">e</kbd> {{ __('export') }}</span>
        </div>
    </div>
</x-layouts.admin>
