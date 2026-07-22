<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Reconciliation\ReconciliationService;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\ReconciliationRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin treasury & solvency (DollarHub structure — controller + Blade, not
 * Livewire). Read-only per-asset solvency view; reconciliation is a form POST.
 */
class TreasuryController extends Controller
{
    public function index(): View
    {
        $this->authorizeTreasury();

        $assets = Asset::where('is_active', true)->orderBy('sort')->get();

        $latestByAsset = $assets->mapWithKeys(fn (Asset $asset) => [
            $asset->id => ReconciliationRun::where('asset_id', $asset->id)->latest()->first(),
        ]);

        $recentRuns = ReconciliationRun::with('asset')->latest()->limit(20)->get();

        return view('admin.treasury', [
            'assets' => $assets,
            'latestByAsset' => $latestByAsset,
            'recentRuns' => $recentRuns,
        ]);
    }

    public function reconcile(): RedirectResponse
    {
        $this->authorizeTreasury();

        $runs = app(ReconciliationService::class)->runAll();
        $insolvent = collect($runs)->reject(fn (ReconciliationRun $r) => $r->is_solvent)->count();

        if ($insolvent > 0) {
            return back()->with('error', "Reconciliation complete — {$insolvent} asset(s) INSOLVENT.");
        }

        return back()->with('success', 'Reconciliation complete — all assets solvent.');
    }

    private function authorizeTreasury(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-treasury') || $admin->hasRole('super-admin'), 403);
    }
}
