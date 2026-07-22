<?php

declare(strict_types=1);

namespace App\Domain\Wallet;

use App\Domain\Ledger\AccountResolver;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Read-model over the ledger for user wallets. A "wallet" is just the pair of
 * user:available + user:locked ledger accounts for an asset (TDD §5.2).
 */
class WalletService
{
    public function __construct(private readonly AccountResolver $accounts) {}

    /**
     * Full wallet snapshot for a user — one wallet per COIN (currency), not per
     * chain. USDT on three chains is a single pooled balance. The representative
     * asset is the coin's canonical (lowest-id) network so it matches the pooled
     * ledger account.
     *
     * @return Collection<int, WalletBalance>
     */
    public function walletsFor(User $user): Collection
    {
        return Asset::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('symbol')
            ->get()
            ->groupBy(fn (Asset $asset) => $asset->currency_id ?? $asset->symbol)
            ->map(fn (Collection $group) => $this->balanceFor($user, $group->sortBy('id')->first()))
            ->values();
    }

    public function balanceFor(User $user, Asset $asset): WalletBalance
    {
        $available = $this->accounts->forUser($user, LedgerAccountType::UserAvailable, $asset->id);
        $locked = $this->accounts->forUser($user, LedgerAccountType::UserLocked, $asset->id);

        return new WalletBalance(
            asset: $asset,
            available: $this->money($available, $asset),
            locked: $this->money($locked, $asset),
        );
    }

    /** Only assets the user actually holds a non-zero balance in. */
    public function fundedWallets(User $user): Collection
    {
        return $this->walletsFor($user)->filter(fn (WalletBalance $w) => ! $w->total()->isZero())->values();
    }

    private function money(LedgerAccount $account, Asset $asset): Money
    {
        $base = $account->balance?->balance ?? '0';

        return Money::ofBase($base, $asset->decimals, $asset->symbol);
    }
}
