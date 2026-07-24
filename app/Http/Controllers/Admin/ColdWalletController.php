<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AssetKind;
use App\Enums\LedgerAccountType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\ColdRefillRequest;
use App\Models\CustodyXpub;
use App\Support\TreasuryBalances;
use Illuminate\View\View;

/**
 * Cold Wallet — "Are cold reserves safe and are refills pending?" Per-chain
 * watch-only xpubs, the treasury:cold ledger reserve per asset, and the
 * cold→hot refill request queue. Read-only.
 */
class ColdWalletController extends Controller
{
    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-treasury') || $admin->hasRole('super-admin'), 403);

        $chains = Chain::query()
            ->with(['assets' => fn ($q) => $q->where('kind', AssetKind::Crypto->value)->where('is_active', true)->orderBy('sort')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $wallets = [];
        $coldWatchCount = 0;

        foreach ($chains as $chain) {
            $coldWatch = CustodyXpub::where('chain_id', $chain->id)
                ->where('purpose', 'cold-watch')
                ->orderBy('label')
                ->get(['id', 'label', 'xpub', 'derivation_path', 'is_active']);
            $coldWatchCount += $coldWatch->count();

            $assets = [];
            foreach ($chain->assets as $asset) {
                $cold = TreasuryBalances::of(LedgerAccountType::TreasuryCold, $asset);
                $assets[] = ['symbol' => $asset->symbol, 'cold' => $cold->format(), 'zero' => $cold->isZero()];
            }

            $wallets[] = ['chain' => $chain, 'coldWatch' => $coldWatch, 'assets' => $assets];
        }

        // Asset/chain symbol maps for the refill queue (ColdRefillRequest has no relations).
        $assetSymbols = Asset::pluck('symbol', 'id');
        $chainNames = Chain::pluck('name', 'id');

        return view('admin.cold-wallet', [
            'wallets' => $wallets,
            'chainCount' => $chains->count(),
            'coldWatchCount' => $coldWatchCount,
            'refills' => ColdRefillRequest::latest()->limit(25)->get(),
            'assetSymbols' => $assetSymbols,
            'chainNames' => $chainNames,
        ]);
    }
}
