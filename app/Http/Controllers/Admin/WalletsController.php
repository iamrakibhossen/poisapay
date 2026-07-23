<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Enums\AssetKind;
use App\Enums\LedgerAccountType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\CustodyXpub;
use App\Models\GasWallet;
use App\Models\LedgerLine;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\View\View;
use Throwable;

/**
 * Admin custody wallets — a focused, read-only view of where platform funds live:
 * the per-chain HOT wallet (funds withdrawals; address derived from the custody
 * signer) and COLD storage (watch-only xpubs + the treasury:cold ledger reserve).
 * Balances come from the ledger (debit − credit on the treasury accounts), so the
 * page works identically in simulated and real-custody modes.
 */
class WalletsController extends Controller
{
    public function index(SignerKeyProvider $signer): View
    {
        $this->authorizeTreasury();

        $chains = Chain::query()
            ->with(['assets' => fn ($q) => $q->where('kind', AssetKind::Crypto->value)->where('is_active', true)->orderBy('sort')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $wallets = [];
        $hotConfigured = 0;
        $coldWatchCount = 0;
        $lowGasCount = 0;

        foreach ($chains as $chain) {
            // Hot wallet address is derived from the custody signer (m/44'/coin'/0'/1/0).
            // In demo mode without a seed configured this throws — degrade gracefully.
            try {
                $hotAddress = $signer->hotWalletAddress($chain->key);
            } catch (Throwable) {
                $hotAddress = null;
            }

            $gas = GasWallet::where('chain_id', $chain->id)->first();
            $gasLow = $gas?->isLow() ?? false;

            $coldWatch = CustodyXpub::where('chain_id', $chain->id)
                ->where('purpose', 'cold-watch')
                ->orderBy('label')
                ->get(['id', 'label', 'xpub', 'derivation_path', 'is_active']);

            $assets = [];
            foreach ($chain->assets as $asset) {
                $hot = $this->treasuryBalance(LedgerAccountType::TreasuryHot, $asset);
                $cold = $this->treasuryBalance(LedgerAccountType::TreasuryCold, $asset);
                $assets[] = [
                    'symbol' => $asset->symbol,
                    'contract' => $asset->contract_address,
                    'hot' => $hot->format(),
                    'cold' => $cold->format(),
                    'hotZero' => $hot->isZero(),
                    'coldZero' => $cold->isZero(),
                ];
            }

            $hotConfigured += $hotAddress ? 1 : 0;
            $coldWatchCount += $coldWatch->count();
            $lowGasCount += $gasLow ? 1 : 0;

            $wallets[] = [
                'chain' => $chain,
                'hotAddress' => $hotAddress,
                'hotExplorer' => $hotAddress ? $chain->explorerAddressUrl($hotAddress) : null,
                'gasBalance' => $gas ? $gas->money()->format() : null,
                'gasSymbol' => $chain->native_symbol,
                'gasLow' => $gasLow,
                'coldWatch' => $coldWatch,
                'assets' => $assets,
            ];
        }

        return view('admin.wallets', [
            'wallets' => $wallets,
            'simulated' => (bool) config('poisapay.custody_simulated'),
            'chainCount' => $chains->count(),
            'hotConfigured' => $hotConfigured,
            'coldWatchCount' => $coldWatchCount,
            'lowGasCount' => $lowGasCount,
        ]);
    }

    /** Treasury balance for an asset = debit − credit (treasury accounts are debit-normal). */
    private function treasuryBalance(LedgerAccountType $type, Asset $asset): Money
    {
        $scope = fn ($q) => $q->where('type', $type->value)->where('asset_id', $asset->id);

        $credit = (string) LedgerLine::whereHas('account', $scope)->where('side', 'credit')->sum('amount');
        $debit = (string) LedgerLine::whereHas('account', $scope)->where('side', 'debit')->sum('amount');

        return Money::ofBase(BigInteger::of($debit)->minus($credit), $asset->decimals, $asset->symbol);
    }

    private function authorizeTreasury(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-treasury') || $admin->hasRole('super-admin'), 403);
    }
}
