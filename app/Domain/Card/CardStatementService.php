<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Enums\CardAuthStatus;
use App\Models\Card;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Monthly card statement + spend analytics (TDD §F3.7). Aggregates a card's
 * authorisations over a period into settled totals, refunds, and a per-MCC
 * breakdown — all in settlement-currency minor units (no crypto conversion here).
 */
class CardStatementService
{
    /** @return array{card: Card, from: string, to: string, lines: Collection, settled_minor: int, refunded_minor: int, count: int, by_mcc: array<string, int>} */
    public function forPeriod(Card $card, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $lines = $card->authorizations()
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get();

        $settled = $lines->where('status', CardAuthStatus::Settled);
        $refunded = $lines->where('status', CardAuthStatus::Reversed);

        $byMcc = $settled
            ->groupBy(fn ($a) => $a->mcc ?: 'other')
            ->map(fn ($group) => (int) $group->sum('amount'))
            ->sortDesc()
            ->all();

        return [
            'card' => $card,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'lines' => $lines,
            'settled_minor' => (int) $settled->sum('amount'),
            'refunded_minor' => (int) $refunded->sum('amount'),
            'count' => $settled->count(),
            'by_mcc' => $byMcc,
        ];
    }

    /** Rolling spend analytics for a user's whole card portfolio (admin + user dashboards). */
    public function analytics(Card $card, int $months = 6): Collection
    {
        $since = CarbonImmutable::now()->subMonths($months)->startOfMonth();

        return $card->authorizations()
            ->where('status', CardAuthStatus::Settled)
            ->where('created_at', '>=', $since)
            ->get()
            ->groupBy(fn ($a) => CarbonImmutable::parse($a->created_at)->format('Y-m'))
            ->map(fn ($group) => [
                'spent_minor' => (int) $group->sum('amount'),
                'count' => $group->count(),
            ])
            ->sortKeys();
    }
}
