<?php

declare(strict_types=1);

namespace App\Domain\Spending;

use App\Domain\Ledger\AccountResolver;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves the ORDER in which a user's balances are spent — the single source of
 * truth for spend priority across the platform.
 *
 * A user's own configured priority (user_spending_priority) always wins and is
 * honoured verbatim. Otherwise the configurable system default is used
 * (config/spending.php), preferring the settlement asset first so a same-currency
 * balance is spent 1:1 with no conversion/spread. Either way the settlement asset
 * and every remaining active asset are appended as fallbacks so a spend never
 * fails while funds exist. Results are canonical per coin (RedotPay pooling).
 */
class SpendingPriorityResolver
{
    public function __construct(private readonly AccountResolver $accounts) {}

    /** @return Collection<int, Asset> ordered candidate assets, de-duped per coin */
    public function resolve(User $user, ?Asset $settlement = null, bool $settlementFirst = true): Collection
    {
        $ordered = collect();

        $custom = $user->spendingPriority()->with('asset')->get()
            ->pluck('asset')->filter()->values();

        if ($custom->isNotEmpty()) {
            // Custom priority overrides the default and is followed exactly.
            $custom->each(fn (Asset $a) => $ordered->push($a));
        } else {
            if ($settlementFirst && $settlement) {
                $ordered->push($settlement);
            }
            foreach ((array) config('spending.default_priority', []) as $symbol) {
                if ($asset = $this->bySymbol((string) $symbol)) {
                    $ordered->push($asset);
                }
            }
        }

        // Keep the settlement asset reachable even if a custom list omits it.
        if ($settlement) {
            $ordered->push($settlement);
        }

        // "Remaining supported assets": any funded balance can still be drawn last.
        if (config('spending.include_remaining', true)) {
            Asset::where('is_active', true)->orderBy('sort')->orderBy('id')->get()
                ->each(fn (Asset $a) => $ordered->push($a));
        }

        return $ordered->filter()
            ->unique(fn (Asset $a) => $this->accounts->canonicalAssetId($a->id))
            ->values();
    }

    private function bySymbol(string $symbol): ?Asset
    {
        return Asset::where('is_active', true)
            ->where('symbol', strtoupper($symbol))
            ->orderBy('id')->first();
    }
}
