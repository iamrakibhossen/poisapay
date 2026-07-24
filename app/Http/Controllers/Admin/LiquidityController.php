<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AssetKind;
use App\Enums\LedgerAccountType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Support\TreasuryBalances;
use Illuminate\View\View;

/**
 * Liquidity — "How much treasury reserve backs each asset?" Per-asset hot / cold
 * / pending ledger balances and their total. Read-only aggregation over the
 * ledger (via TreasuryBalances); no business logic.
 */
class LiquidityController extends Controller
{
    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-treasury') || $admin->hasRole('super-admin'), 403);

        $assets = Asset::where('kind', AssetKind::Crypto->value)->where('is_active', true)->orderBy('sort')->get();

        $rows = $assets->map(function (Asset $asset) {
            $hot = TreasuryBalances::of(LedgerAccountType::TreasuryHot, $asset);
            $cold = TreasuryBalances::of(LedgerAccountType::TreasuryCold, $asset);
            $pending = TreasuryBalances::of(LedgerAccountType::TreasuryPending, $asset);
            $total = $hot->plus($cold)->plus($pending);

            return [
                'symbol' => $asset->symbol,
                'chain' => $asset->chain?->name,
                'hot' => $hot->format(),
                'cold' => $cold->format(),
                'pending' => $pending->format(),
                'total' => $total->format(),
                'zero' => $total->isZero(),
            ];
        });

        return view('admin.liquidity', ['rows' => $rows]);
    }
}
