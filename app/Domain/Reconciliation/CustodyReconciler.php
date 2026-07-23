<?php

declare(strict_types=1);

namespace App\Domain\Reconciliation;

use App\Domain\Chain\Evm\HotWalletManager;
use App\Domain\Chain\Tron\TronGridClient;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Ledger\AccountResolver;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\Chain;
use Throwable;

/**
 * On-chain custody probe + hot-backing reconciliation. It reads the real
 * hot-wallet balance from chain and compares it against the {@see LedgerAccountType::TreasuryHot}
 * ledger balance so a divergence (e.g. a sweep that moved the books but not the coins)
 * is visible. This is the on-chain leg that complements the ledger-internal solvency
 * invariant in {@see ReconciliationService}.
 *
 * Read-only — never writes to the ledger. No-op under simulated custody (no real chain,
 * no seed to derive the hot address). Reuses the EVM primitive
 * {@see HotWalletManager::reconcileErc20()} and the TRON {@see TronGridClient::tokenBalance()}.
 */
class CustodyReconciler
{
    public function __construct(
        private readonly AccountResolver $accounts,
        private readonly HotWalletManager $evmHot,
        private readonly TronGridClient $tron,
        private readonly SignerKeyProvider $keys,
    ) {}

    /**
     * On-chain hot-wallet balance for a single token asset, in base units — or null
     * when it can't be read (simulated custody, no contract, inactive chain, no seed,
     * or an RPC error). Reused by {@see ReconciliationService} to populate a run's
     * on-chain controlled figure.
     */
    public function hotBalanceBase(Asset $asset): ?string
    {
        if (config('poisapay.custody_simulated') || $asset->contract_address === null) {
            return null;
        }

        $chain = $asset->chain;
        if ($chain === null || ! $chain->is_active) {
            return null;
        }

        try {
            $hotAddress = $this->keys->hotWalletAddress($chain->key);

            return $chain->is_evm
                ? (string) $this->evmHot->reconcileErc20($chain->key, $asset, $hotAddress)['onchain']
                : $this->tron->tokenBalance($hotAddress, (string) $asset->contract_address);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Compare on-chain hot balance vs the treasury:hot ledger for every active token
     * asset and flag drift beyond tolerance (alerting operators). Read-only.
     *
     * @return list<array{chain: string, asset: string, onchain: string, ledger: string, drift: string, breached: bool, error: ?string}>
     */
    public function reconcile(?string $toleranceBase = null): array
    {
        if (config('poisapay.custody_simulated')) {
            return []; // nothing real to reconcile under simulated custody
        }

        $tolerance = $toleranceBase ?? (string) config('poisapay.custody.reconcile_tolerance', '0');
        $report = [];

        foreach (Chain::where('is_active', true)->get() as $chain) {
            $assets = Asset::where('chain_id', $chain->id)
                ->where('is_active', true)
                ->whereNotNull('contract_address')
                ->get();

            foreach ($assets as $asset) {
                $row = [
                    'chain' => $chain->key->value,
                    'asset' => (string) $asset->symbol,
                    'onchain' => '0',
                    'ledger' => '0',
                    'drift' => '0',
                    'breached' => false,
                    'error' => null,
                ];

                $onchain = $this->hotBalanceBase($asset);
                if ($onchain === null) {
                    $row['error'] = 'on-chain balance unavailable';
                    $report[] = $row;

                    continue;
                }

                $ledger = ltrim(
                    $this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id)
                        ->fresh('balance')->money()->baseString(),
                    '-',
                );

                $row['onchain'] = $onchain;
                $row['ledger'] = $ledger;
                $row['drift'] = bcsub($onchain, $ledger);
                $row['breached'] = bccomp(ltrim($row['drift'], '-'), $tolerance) > 0;

                if ($row['breached']) {
                    notifyAdmins(
                        'Custody reconciliation drift',
                        "{$row['asset']} on {$row['chain']}: on-chain hot={$row['onchain']}, ledger treasury:hot={$row['ledger']}, drift={$row['drift']} (base units). Investigate before it becomes a solvency gap.",
                        null,
                        'security',
                    );
                }

                $report[] = $row;
            }
        }

        return $report;
    }
}
