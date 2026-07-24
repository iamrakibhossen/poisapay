<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Period-over-period comparison for a single scalar metric. Produces the
 * "↑ +12.5%" delta and its direction so KPI cards render a consistent trend
 * chip. `higherIsBetter` flips the colour semantics for cost/loss metrics where
 * a rise is bad.
 */
final class Trend
{
    /**
     * @return array{
     *   delta: float|null,
     *   direction: 'up'|'down'|'flat',
     *   good: bool,
     *   label: string,
     *   current: float,
     *   previous: float,
     * }
     */
    public static function compute(float|int $current, float|int $previous, bool $higherIsBetter = true): array
    {
        $current = (float) $current;
        $previous = (float) $previous;

        if ($previous == 0.0) {
            // No baseline: report absolute movement without a misleading percentage.
            $direction = $current > 0 ? 'up' : ($current < 0 ? 'down' : 'flat');

            return [
                'delta' => $current == 0.0 ? 0.0 : null,
                'direction' => $direction,
                'good' => $direction === 'flat' ? true : (($direction === 'up') === $higherIsBetter),
                'label' => $current == 0.0 ? 'No change' : ($current > 0 ? 'New' : '—'),
                'current' => $current,
                'previous' => $previous,
            ];
        }

        $delta = round((($current - $previous) / abs($previous)) * 100, 1);
        $direction = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');

        return [
            'delta' => $delta,
            'direction' => $direction,
            'good' => $direction === 'flat' ? true : (($direction === 'up') === $higherIsBetter),
            'label' => ($delta > 0 ? '+' : '').$delta.'%',
            'current' => $current,
            'previous' => $previous,
        ];
    }
}
