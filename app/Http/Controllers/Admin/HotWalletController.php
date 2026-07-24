<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Enums\AssetKind;
use App\Enums\LedgerAccountType;
use App\Http\Controllers\Controller;
use App\Models\Chain;
use App\Models\GasWallet;
use App\Support\TreasuryBalances;
use Illuminate\View\View;
use Throwable;

/**
 * Hot Wallet — "Are the withdrawal-serving wallets funded?" Per-chain hot address
 * (derived from the custody signer), its gas balance, and the treasury:hot ledger
 * reserve per asset. Read-only; balances come from the ledger so it works in both
 * simulated and real-custody modes.
 */
class HotWalletController extends Controller
{
    public function index(SignerKeyProvider $signer): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-treasury') || $admin->hasRole('super-admin'), 403);

        $chains = Chain::query()
            ->with(['assets' => fn ($q) => $q->where('kind', AssetKind::Crypto->value)->where('is_active', true)->orderBy('sort')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $wallets = [];
        $lowGasCount = 0;

        foreach ($chains as $chain) {
            try {
                $hotAddress = $signer->hotWalletAddress($chain->key);
            } catch (Throwable) {
                $hotAddress = null;
            }

            $gas = GasWallet::where('chain_id', $chain->id)->first();
            $gasLow = $gas?->isLow() ?? false;
            $lowGasCount += $gasLow ? 1 : 0;

            $assets = [];
            foreach ($chain->assets as $asset) {
                $hot = TreasuryBalances::of(LedgerAccountType::TreasuryHot, $asset);
                $assets[] = ['symbol' => $asset->symbol, 'hot' => $hot->format(), 'zero' => $hot->isZero()];
            }

            $wallets[] = [
                'chain' => $chain,
                'hotAddress' => $hotAddress,
                'hotExplorer' => $hotAddress ? $chain->explorerAddressUrl($hotAddress) : null,
                'gasBalance' => $gas ? $gas->money()->format() : null,
                'gasSymbol' => $chain->native_symbol,
                'gasLow' => $gasLow,
                'assets' => $assets,
            ];
        }

        return view('admin.hot-wallet', [
            'wallets' => $wallets,
            'simulated' => (bool) config('poisapay.custody_simulated'),
            'chainCount' => $chains->count(),
            'hotConfigured' => collect($wallets)->filter(fn ($w) => $w['hotAddress'])->count(),
            'lowGasCount' => $lowGasCount,
        ]);
    }
}
