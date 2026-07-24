@props([
    'chart' => [],
])

@php
    $span = ($chart['span'] ?? 'full') === 'full' ? 'lg:col-span-2' : '';
    $domId = 'chart-'.($chart['id'] ?? uniqid());
@endphp

<div class="pp-card p-5 {{ $span }}">
    <div class="mb-4 flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="truncate text-sm font-semibold text-neutral-900">{{ $chart['title'] ?? '' }}</h3>
            @if (! empty($chart['subtitle']))
                <p class="mt-0.5 text-xs text-neutral-400">{{ $chart['subtitle'] }}</p>
            @endif
        </div>
    </div>

    <div
        x-data="{
            empty: false,
            init() {
                const c = @js($chart);
                const palette = ['#f59e0b','#10b981','#0ea5e9','#8b5cf6','#f43f5e','#14b8a6','#6366f1','#ec4899','#eab308','#64748b'];
                const isDoughnut = c.type === 'doughnut';
                const isArea = c.type === 'area';
                const isStacked = c.type === 'stacked-bar';
                const chartType = isDoughnut ? 'doughnut' : ((isArea || c.type === 'line') ? 'line' : 'bar');

                const fmt = (n) => {
                    const a = Math.abs(Number(n) || 0);
                    if (a >= 1e9) return (n / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
                    if (a >= 1e6) return (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
                    if (a >= 1e3) return (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
                    return String(Math.round(n * 100) / 100);
                };

                // Nothing to show → clean empty state instead of a blank/broken canvas.
                this.empty = ! (c.datasets || []).some((ds) => (ds.data || []).some((v) => Number(v) !== 0));
                if (this.empty) { this.ready = true; return; }

                const datasets = (c.datasets || []).map((ds, i) => {
                    const base = ds.color || palette[i % palette.length];
                    if (isDoughnut) {
                        return {
                            data: ds.data,
                            backgroundColor: (c.labels || []).map((_, j) => palette[j % palette.length]),
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 6,
                        };
                    }
                    let bg = base;
                    if (isArea) {
                        const grad = this.$refs.canvas.getContext('2d').createLinearGradient(0, 0, 0, 240);
                        grad.addColorStop(0, base + '33');
                        grad.addColorStop(1, base + '00');
                        bg = grad;
                    }
                    return {
                        label: ds.label,
                        data: ds.data,
                        borderColor: base,
                        backgroundColor: bg,
                        fill: isArea,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: base,
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        borderRadius: chartType === 'bar' ? 6 : 0,
                        maxBarThickness: 44,
                    };
                });

                const multi = datasets.length > 1;
                const scales = isDoughnut ? {} : {
                    x: { stacked: isStacked, grid: { display: false }, border: { display: false }, ticks: { color: '#9ca3af', maxRotation: 0, autoSkip: true, maxTicksLimit: 8, font: { size: 11 } } },
                    y: { stacked: isStacked, beginAtZero: true, border: { display: false }, grid: { color: 'rgba(148,163,184,0.14)' }, ticks: { color: '#9ca3af', maxTicksLimit: 5, font: { size: 11 }, callback: (v) => fmt(v) } },
                };

                // Total in the middle of every doughnut.
                const centerText = {
                    id: 'centerText',
                    afterDraw(ch) {
                        if (ch.config.type !== 'doughnut') return;
                        const { ctx, chartArea: { left, right, top, bottom } } = ch;
                        const total = ch.data.datasets[0].data.reduce((a, b) => a + (Number(b) || 0), 0);
                        const cx = (left + right) / 2, cy = (top + bottom) / 2;
                        ctx.save();
                        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                        ctx.fillStyle = '#0f172a'; ctx.font = '700 17px Inter, ui-sans-serif, sans-serif';
                        ctx.fillText(fmt(total), cx, cy - 3);
                        ctx.fillStyle = '#9ca3af'; ctx.font = '600 10px Inter, ui-sans-serif, sans-serif';
                        ctx.fillText('TOTAL', cx, cy + 14);
                        ctx.restore();
                    },
                };

                window.ppChart(this.$refs.canvas, {
                    type: chartType,
                    data: { labels: c.labels || [], datasets },
                    plugins: isDoughnut ? [centerText] : [],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 600, easing: 'easeOutQuart' },
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: {
                                display: multi || isDoughnut,
                                position: isDoughnut ? 'right' : 'top',
                                align: isDoughnut ? 'center' : 'end',
                                labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', color: '#6b7280', padding: 14, font: { size: 11 } },
                            },
                            tooltip: {
                                padding: 12, backgroundColor: '#0f172a', cornerRadius: 10, boxPadding: 6,
                                titleColor: '#f8fafc', bodyColor: '#cbd5e1', usePointStyle: true,
                                callbacks: { label: (ctx) => '  ' + (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + fmt(ctx.parsed.y ?? ctx.parsed) },
                            },
                        },
                        cutout: isDoughnut ? '66%' : undefined,
                        scales,
                    },
                });
            }
        }"
        class="relative {{ $span ? 'h-[300px]' : 'h-[280px]' }}"
    >
        {{-- Empty state --}}
        <div x-show="empty" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-center">
            <span class="grid h-11 w-11 place-items-center rounded-full bg-neutral-100 text-neutral-300">
                <x-heroicon-o-chart-bar class="h-5 w-5" />
            </span>
            <p class="text-sm font-medium text-neutral-400">{{ __('No data for this period') }}</p>
        </div>

        <canvas x-ref="canvas" id="{{ $domId }}" x-show="! empty"></canvas>
    </div>
</div>
