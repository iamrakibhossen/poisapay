<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A resolved analytics date window plus its comparison ("previous") window.
 *
 * Presets map to a concrete [start, end] pair; every widget filters by the same
 * Period so the numbers never disagree. Comparison mode diffs the current window
 * against {@see previous()} — a like-for-like window immediately preceding it
 * (calendar-aware for month/quarter/year, duration-shifted for rolling ranges).
 */
final class Period
{
    /** @var array<string,string> preset key => human label */
    public const PRESETS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'last_7_days' => 'Last 7 Days',
        'last_30_days' => 'Last 30 Days',
        'this_month' => 'This Month',
        'previous_month' => 'Previous Month',
        'this_quarter' => 'This Quarter',
        'previous_quarter' => 'Previous Quarter',
        'this_year' => 'This Year',
        'previous_year' => 'Previous Year',
        'custom' => 'Custom Range',
    ];

    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    /** Resolve a preset (or an explicit custom [from, to]) into a concrete window. */
    public static function resolve(?string $key, ?string $from = null, ?string $to = null): self
    {
        $key = array_key_exists((string) $key, self::PRESETS) ? $key : 'last_30_days';
        $now = CarbonImmutable::now();

        [$start, $end] = match ($key) {
            'today' => [$now->startOfDay(), $now],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now],
            'last_30_days' => [$now->subDays(29)->startOfDay(), $now],
            'this_month' => [$now->startOfMonth(), $now],
            'previous_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->startOfQuarter(), $now],
            'previous_quarter' => [$now->subMonthsNoOverflow(3)->startOfQuarter(), $now->subMonthsNoOverflow(3)->endOfQuarter()],
            'this_year' => [$now->startOfYear(), $now],
            'previous_year' => [$now->subYearNoOverflow()->startOfYear(), $now->subYearNoOverflow()->endOfYear()],
            'custom' => self::customBounds($from, $to, $now),
            default => [$now->subDays(29)->startOfDay(), $now],
        };

        $label = $key === 'custom'
            ? $start->format('M j, Y').' – '.$end->format('M j, Y')
            : self::PRESETS[$key];

        return new self($key, $label, $start, $end);
    }

    /** The like-for-like window immediately preceding this one, for comparison mode. */
    public function previous(): self
    {
        return match ($this->key) {
            'today' => self::window('previous', 'vs yesterday', $this->start->subDay(), $this->end->subDay()),
            'yesterday' => self::window('previous', 'vs prior day', $this->start->subDay(), $this->end->subDay()),
            'this_month' => self::window('previous', 'vs last month', $this->start->subMonthNoOverflow(), $this->end->subMonthNoOverflow()),
            'previous_month' => self::window('previous', 'vs prior month', $this->start->subMonthNoOverflow(), $this->end->subMonthNoOverflow()),
            'this_quarter' => self::window('previous', 'vs last quarter', $this->start->subMonthsNoOverflow(3), $this->end->subMonthsNoOverflow(3)),
            'previous_quarter' => self::window('previous', 'vs prior quarter', $this->start->subMonthsNoOverflow(3), $this->end->subMonthsNoOverflow(3)),
            'this_year' => self::window('previous', 'vs last year', $this->start->subYearNoOverflow(), $this->end->subYearNoOverflow()),
            'previous_year' => self::window('previous', 'vs prior year', $this->start->subYearNoOverflow(), $this->end->subYearNoOverflow()),
            // Rolling / custom windows: shift back by the exact duration.
            default => self::window('previous', 'vs prior period', $this->start->sub($this->duration()), $this->start->subSecond()),
        };
    }

    /** Whole seconds spanned, used to shift rolling comparison windows. */
    public function duration(): \DateInterval
    {
        return $this->start->diffAsCarbonInterval($this->end)->toDateInterval();
    }

    public function days(): int
    {
        return (int) $this->start->startOfDay()->diffInDays($this->end->startOfDay()) + 1;
    }

    /** Series bucket granularity chosen from the window length. */
    public function granularity(): string
    {
        $days = $this->days();

        return match (true) {
            $days <= 2 => 'hour',
            $days <= 92 => 'day',
            default => 'month',
        };
    }

    /**
     * Ordered, gap-free buckets covering the window at {@see granularity()}.
     * Reports left-join their grouped counts onto these so charts never skip an
     * empty hour/day/month.
     *
     * @return list<array{key:string,label:string}>
     */
    public function buckets(): array
    {
        [$step, $keyFmt, $labelFmt] = match ($this->granularity()) {
            'hour' => ['hour', 'Y-m-d H:00', 'ga'],
            'month' => ['month', 'Y-m', 'M Y'],
            default => ['day', 'Y-m-d', 'M j'],
        };

        $out = [];
        $cursor = $this->granularity() === 'hour'
            ? $this->start->startOfHour()
            : ($this->granularity() === 'month' ? $this->start->startOfMonth() : $this->start->startOfDay());

        while ($cursor->lessThanOrEqualTo($this->end)) {
            $out[] = ['key' => $cursor->format($keyFmt), 'label' => $cursor->format($labelFmt)];
            $cursor = $cursor->add($step, 1);
        }

        return $out;
    }

    /** SQL bucket expression (pgsql) that produces keys matching {@see buckets()}. */
    public function bucketExpression(string $column = 'created_at'): string
    {
        $fmt = match ($this->granularity()) {
            'hour' => 'YYYY-MM-DD HH24:00',
            'month' => 'YYYY-MM',
            default => 'YYYY-MM-DD',
        };

        return "to_char($column, '$fmt')";
    }

    /**
     * Stable cache signature for this window.
     *
     * Bucketed to the hour: rolling windows (today / last_30_days / this_month …)
     * are anchored to now()-to-the-second, so an exact signature would change every
     * request and the report cache would NEVER hit — re-running the full ledger
     * aggregation on every page load. `start`/`end` stay exact for the actual
     * queries; only the cache KEY is coarsened, which matches the 1h TTL and the
     * hourly rollup/flush cadence.
     */
    public function signature(): string
    {
        $bucket = AnalyticsCache::TTL; // 3600s — align the key window with the TTL

        return $this->key.':'.intdiv($this->start->timestamp, $bucket).':'.intdiv($this->end->timestamp, $bucket);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
        ];
    }

    private static function window(string $key, string $label, CarbonInterface $start, CarbonInterface $end): self
    {
        return new self($key, $label, CarbonImmutable::instance($start), CarbonImmutable::instance($end));
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    private static function customBounds(?string $from, ?string $to, CarbonImmutable $now): array
    {
        try {
            $start = $from ? CarbonImmutable::parse($from)->startOfDay() : $now->subDays(29)->startOfDay();
            $end = $to ? CarbonImmutable::parse($to)->endOfDay() : $now;
        } catch (\Throwable) {
            return [$now->subDays(29)->startOfDay(), $now];
        }

        return $start->lessThanOrEqualTo($end) ? [$start, $end] : [$end->startOfDay(), $start->endOfDay()];
    }
}
