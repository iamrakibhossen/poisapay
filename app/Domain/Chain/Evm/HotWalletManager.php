<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Ledger\AccountResolver;
use App\Enums\ChainType;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\GasWallet;

/**
 * EVM hot-wallet + gas management and on-chain reconciliation (Wave 2). Syncs the
 * native gas balance into the GasWallet (alerting operators when it runs low) and
 * compares the hot wallet's on-chain ERC-20 balance against the ledger treasury so
 * drift is visible.
 */
class HotWalletManager
{
    public function __construct(
        private readonly BlockchainProvider $chain,
        private readonly AccountResolver $accounts,
    ) {}

    /**
     * Pull the native balance from chain into the GasWallet and raise a tiered
     * alert: WARNING (top up soon, withdrawals continue) or CRITICAL (cannot pay
     * gas — withdrawals on this chain are held until refilled).
     */
    public function syncGas(ChainType $chainType): ?GasWallet
    {
        $chain = Chain::where('key', $chainType->value)->first();
        $wallet = $chain ? GasWallet::where('chain_id', $chain->id)->first() : null;
        if (! $wallet || ! $wallet->address) {
            return null;
        }

        $wallet->update(['balance' => $this->chain->getBalance($chainType, $wallet->address)]);
        $wallet = $wallet->fresh();

        // Alert only on a WARNING-marker breach (min_threshold) or worse. A balance
        // below the healthy target but still at/above the warning marker is a Warning
        // *status* (see health()) that does NOT page operators — the wallet can still
        // comfortably pay gas. Critical additionally holds withdrawals (canPayGas()).
        if ($wallet->isLow()) {
            $critical = $wallet->isCritical();
            notifyAdmins(
                $critical ? "Critical: {$chainType->value} gas wallet depleted" : "Low gas wallet ({$chainType->value})",
                $critical
                    ? "The {$chainType->value} gas wallet is CRITICALLY LOW (balance {$wallet->balance} wei) and can no longer reliably pay network gas — withdrawals on this chain are paused until it is topped up."
                    : "The {$chainType->value} gas wallet is below its warning threshold (balance {$wallet->balance} wei) — still operating, but top it up before it nears the critical level.",
                null,
                'security',
            );
        }

        return $wallet;
    }

    /**
     * On-chain hot-wallet ERC-20 balance vs the ledger treasury:hot balance.
     *
     * @return array{onchain: string, ledger: string, drift: string}
     */
    public function reconcileErc20(ChainType $chainType, Asset $asset, string $hotAddress): array
    {
        $result = $this->chain->call($chainType, (string) $asset->contract_address, Abi::erc20BalanceOf($hotAddress));
        $onchain = Evm::hexToInt($result);

        $ledger = ltrim(
            $this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id)->fresh('balance')->money()->baseString(),
            '-',
        );

        return ['onchain' => $onchain, 'ledger' => $ledger, 'drift' => bcsub($onchain, $ledger)];
    }
}
