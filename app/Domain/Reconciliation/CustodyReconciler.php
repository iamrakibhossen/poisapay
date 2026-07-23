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
 * Read-only custody reconciliation. For every active chain + token asset it compares
 * the on-chain hot-wallet balance against the {@see LedgerAccountType::TreasuryHot}
 * ledger balance and flags drift beyond a tolerance. It NEVER writes to the ledger —
 * it only reports and alerts operators. This is the safety net that catches ledger⇄chain
 * divergence (e.g. a sweep that moved the books but not the coins).
 *
 * No-op under simulated custody: there is no real chain to reconcile against, and the
 * signer has no seed to derive the hot address from. Reuses the existing EVM primitive
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
            $chainType = $chain->key;

            $assets = Asset::where('chain_id', $chain->id)
                ->where('is_active', true)
                ->whereNotNull('contract_address')
                ->get();

            foreach ($assets as $asset) {
                $row = [
                    'chain' => $chainType->value,
                    'asset' => (string) $asset->symbol,
                    'onchain' => '0',
                    'ledger' => '0',
                    'drift' => '0',
                    'breached' => false,
                    'error' => null,
                ];

                try {
                    $hotAddress = $this->keys->hotWalletAddress($chainType);

                    if ($chain->is_evm) {
                        $result = $this->evmHot->reconcileErc20($chainType, $asset, $hotAddress);
                        $row['onchain'] = (string) $result['onchain'];
                        $row['ledger'] = (string) $result['ledger'];
                        $row['drift'] = (string) $result['drift'];
                    } else {
                        $onchain = $this->tron->tokenBalance($hotAddress, (string) $asset->contract_address);
                        $ledger = ltrim(
                            $this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id)
                                ->fresh('balance')->money()->baseString(),
                            '-',
                        );
                        $row['onchain'] = $onchain;
                        $row['ledger'] = $ledger;
                        $row['drift'] = bcsub($onchain, $ledger);
                    }

                    $row['breached'] = bccomp(ltrim($row['drift'], '-'), $tolerance) > 0;

                    if ($row['breached']) {
                        notifyAdmins(
                            'Custody reconciliation drift',
                            "{$row['asset']} on {$row['chain']}: on-chain hot={$row['onchain']}, ledger treasury:hot={$row['ledger']}, drift={$row['drift']} (base units). Investigate before it becomes a solvency gap.",
                            null,
                            'security',
                        );
                    }
                } catch (Throwable $e) {
                    $row['error'] = $e->getMessage();
                }

                $report[] = $row;
            }
        }

        return $report;
    }
}
