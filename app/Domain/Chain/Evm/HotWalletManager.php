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

    /** Pull the native balance from chain into the GasWallet; alert if below threshold. */
    public function syncGas(ChainType $chainType): ?GasWallet
    {
        $chain = Chain::where('key', $chainType->value)->first();
        $wallet = $chain ? GasWallet::where('chain_id', $chain->id)->first() : null;
        if (! $wallet || ! $wallet->address) {
            return null;
        }

        $wallet->update(['balance' => $this->chain->getBalance($chainType, $wallet->address)]);
        $wallet = $wallet->fresh();

        if ($wallet->isLow()) {
            notifyAdmins(
                'Low gas wallet',
                "The {$chainType->value} gas wallet is below its threshold (balance {$wallet->balance} wei). Top it up to keep sweeps/withdrawals flowing.",
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
