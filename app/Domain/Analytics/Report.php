<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Models\AnalyticsDailyMetric;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Base for every analytics section. A report is a pure projection: given a
 * {@see Period} it returns a normalised, JSON/cache-safe structure that the
 * generic section renderer draws without knowing anything about the domain.
 *
 * Return contract (all values are plain scalars/arrays):
 *   kpis   list<array{label,value,trend?,trendUp?,trendGood?,accent?,hint?,icon?}>
 *   charts list<array{id,title,type,labels,datasets,span?,subtitle?}>
 *   tables list<array{title,headers,rows,align?}>
 *   alerts list<array{level,title,message}>   level = critical|warn|info
 *   notes  list<string>                        honest "not tracked yet" states
 */
abstract class Report
{
    /** Machine key used in routes, cache keys and the section registry. */
    abstract public function key(): string;

    /** Human title shown in the section header. */
    abstract public function title(): string;

    /** @return array{kpis:array,charts:array,tables:array,alerts:array,notes:array} */
    abstract public function build(Period $period): array;

    /** Empty normalised envelope for reports to fill. */
    protected function envelope(): array
    {
        return ['kpis' => [], 'charts' => [], 'tables' => [], 'alerts' => [], 'notes' => []];
    }

    /** A plain KPI card. */
    protected function kpi(string $label, string $value, array $opts = []): array
    {
        return array_merge([
            'label' => $label,
            'value' => $value,
            'accent' => 'brand',
            'trend' => null,
            'hint' => null,
            'icon' => null,
        ], $opts);
    }

    /** A KPI card with a period-over-period trend chip derived from two scalars. */
    protected function trendKpi(string $label, string $value, float $current, float $previous, array $opts = []): array
    {
        $t = Trend::compute($current, $previous, $opts['higherIsBetter'] ?? true);

        return $this->kpi($label, $value, array_merge([
            'trend' => $t['label'],
            'trendUp' => $t['direction'] === 'up',
            'trendGood' => $t['good'],
        ], $opts));
    }

    /**
     * Live count/aggregate series aligned to the period's buckets (zero-filled).
     * Pass a fresh query-builder scoped to the base filter; grouping is added here.
     */
    protected function series(Builder $query, Period $period, string $dateColumn = 'created_at', string $agg = 'count(*)'): array
    {
        $expr = $period->bucketExpression($dateColumn);

        $rows = $query
            ->where($dateColumn, '>=', $period->start)
            ->where($dateColumn, '<=', $period->end)
            ->groupByRaw($expr)
            ->selectRaw("$expr as bucket, $agg as value")
            ->pluck('value', 'bucket');

        return array_map(fn ($b) => (float) ($rows[$b['key']] ?? 0), $period->buckets());
    }

    /**
     * USD-valued daily series read from the materialised summary table, bucketed
     * to the period's granularity. Instant (no ledger scan) — populated by the
     * hourly rollup job.
     */
    protected function summarySeries(string $metric, Period $period): array
    {
        $rows = AnalyticsDailyMetric::where('metric', $metric)
            ->whereBetween('day', [$period->start->toDateString(), $period->end->toDateString()])
            ->pluck('value', 'day');

        $granularity = $period->granularity();

        return array_map(function ($b) use ($rows, $granularity) {
            if ($granularity === 'month') {
                // Sum the daily rows that fall inside this YYYY-MM bucket.
                $sum = 0.0;
                foreach ($rows as $day => $value) {
                    if (str_starts_with((string) $day, $b['key'])) {
                        $sum += (float) $value;
                    }
                }

                return $sum;
            }

            return (float) ($rows[$b['key']] ?? 0);
        }, $period->buckets());
    }

    protected function bucketLabels(Period $period): array
    {
        return array_map(fn ($b) => $b['label'], $period->buckets());
    }

    /** Build a chart descriptor. */
    protected function chart(string $id, string $title, string $type, array $labels, array $datasets, array $opts = []): array
    {
        return array_merge([
            'id' => $id,
            'title' => $title,
            'type' => $type,          // line | area | bar | stacked-bar | doughnut
            'labels' => $labels,
            'datasets' => $datasets,  // list<array{label,data,color?}>
            'span' => 'full',         // full | half
            'subtitle' => null,
        ], $opts);
    }

    protected function dataset(string $label, array $data, ?string $color = null): array
    {
        return ['label' => $label, 'data' => array_values($data), 'color' => $color];
    }

    /** Count rows grouped by an enum/string column → doughnut-ready pairs. */
    protected function countBy(Builder $query, string $column): array
    {
        return $query->groupBy($column)
            ->selectRaw("$column as k, count(*) as c")
            ->pluck('c', 'k')
            ->all();
    }

    protected function fresh(string $table): Builder
    {
        return DB::table($table);
    }

    /** Compact USD display, e.g. "$1,234.56" or "$1.2M" for large figures. */
    protected function usdFmt(string|float|int $value): string
    {
        $v = (float) $value;
        $abs = abs($v);

        return match (true) {
            $abs >= 1_000_000_000 => '$'.rtrim(rtrim(number_format($v / 1_000_000_000, 2), '0'), '.').'B',
            $abs >= 1_000_000 => '$'.rtrim(rtrim(number_format($v / 1_000_000, 2), '0'), '.').'M',
            $abs >= 100_000 => '$'.rtrim(rtrim(number_format($v / 1_000, 1), '0'), '.').'K',
            default => '$'.number_format($v, 2),
        };
    }
}
