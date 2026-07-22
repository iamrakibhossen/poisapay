<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\LedgerAccount;
use App\Models\User;

/**
 * Resolves (and lazily provisions) ledger accounts.
 *
 * System/treasury accounts are keyed per (type, asset) — one per chain, since
 * reserves physically live on a chain. USER balance accounts, however, are
 * pooled per COIN: every network of a currency maps to the same account (its
 * "canonical" asset = the lowest asset id for that currency). So a user holds
 * one "USDT" balance regardless of how many chains it settled on (the RedotPay
 * model). Ledger lines still carry the network asset id for treasury legs, and
 * the balance trigger is asset-agnostic, so pooling stays correct.
 */
class AccountResolver
{
    /** @var array<int, int> assetId → canonical (pooled) assetId for its coin */
    private array $canonical = [];

    /** Get or create a user-owned account. User balances are pooled per coin. */
    public function forUser(User|string $user, LedgerAccountType $type, int $assetId): LedgerAccount
    {
        $userId = $user instanceof User ? $user->getKey() : $user;
        $poolAssetId = $type->isUserAccount() ? $this->canonicalAssetId($assetId) : $assetId;

        return $this->resolve($type, $poolAssetId, $userId);
    }

    /** The pooled/canonical asset id for whatever coin an asset belongs to. */
    public function canonicalAssetId(int $assetId): int
    {
        if (isset($this->canonical[$assetId])) {
            return $this->canonical[$assetId];
        }

        $currencyId = Asset::whereKey($assetId)->value('currency_id');
        $canonical = $currencyId
            ? (int) Asset::where('currency_id', $currencyId)->min('id')
            : $assetId;

        return $this->canonical[$assetId] = $canonical ?: $assetId;
    }

    /** Get or create a system (platform) account — user_id is null. */
    public function system(LedgerAccountType $type, int $assetId): LedgerAccount
    {
        return $this->resolve($type, $assetId, null);
    }

    private function resolve(LedgerAccountType $type, int $assetId, ?string $userId): LedgerAccount
    {
        $account = LedgerAccount::query()
            ->where('type', $type->value)
            ->where('asset_id', $assetId)
            ->where('user_id', $userId)
            ->first();

        if ($account) {
            return $account;
        }

        $account = LedgerAccount::create([
            'type' => $type,
            'user_id' => $userId,
            'asset_id' => $assetId,
            'normal_side' => $type->normalSide(),
            'label' => $type->label(),
        ]);

        // Materialised balance row starts at zero.
        $account->balance()->create(['balance' => '0', 'version' => 0]);

        return $account;
    }

    /** Warm all system accounts for an asset (called by seeders / provisioning). */
    public function ensureSystemAccounts(int $assetId): void
    {
        foreach (LedgerAccountType::cases() as $type) {
            if (! $type->isUserAccount()) {
                $this->system($type, $assetId);
            }
        }
    }
}
