<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Chain\ChainHealthService;
use App\Domain\Reconciliation\ReconciliationService;
use App\Http\Controllers\Controller;
use App\Models\ReconciliationRun;
use App\Models\RpcEndpoint;
use App\Models\Sweep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Admin blockchain health (DollarHub structure — controller + Blade, not
 * Livewire). Read-only chain infrastructure view; the three operations
 * (health check, monitor tick, reconciliation) are form POST actions.
 */
class BlockchainHealthController extends Controller
{
    public function index(): View
    {
        $this->guardAccess();

        $summary = app(ChainHealthService::class)->summary();

        $rpcs = RpcEndpoint::with('chain')
            ->orderBy('chain_id')
            ->orderBy('priority')
            ->get();

        $recentSweeps = Sweep::with('asset')->latest()->limit(10)->get();

        return view('admin.blockchain-health', [
            'summary' => $summary,
            'rpcs' => $rpcs,
            'recentSweeps' => $recentSweeps,
            'totalChains' => $summary->count(),
            'rpcUp' => $summary->sum('rpc_up'),
            'rpcTotal' => $summary->sum('rpc_total'),
            'pendingDeposits' => $summary->sum('pending_deposits'),
            'pendingSweeps' => $summary->sum('pending_sweeps'),
            'gasLowCount' => $summary->where('gas_low', true)->count(),
        ]);
    }

    public function runHealthCheck(): RedirectResponse
    {
        $this->guardAccess();

        Artisan::call('poisapay:chain-health');

        ActivityLogger::log('chain.health.checked', null, [], 'Ran blockchain health check.');

        return back()->with('success', 'Health refreshed.');
    }

    public function runMonitorTick(): RedirectResponse
    {
        $this->guardAccess();

        Artisan::call('poisapay:chain-tick');
        $output = trim(Artisan::output());

        return back()->with('success', $output !== '' ? $output : 'Monitor tick complete.');
    }

    public function runReconciliation(): RedirectResponse
    {
        $this->guardAccess();

        $runs = app(ReconciliationService::class)->runAll();
        $insolvent = collect($runs)->reject(fn (ReconciliationRun $r) => $r->is_solvent)->count();

        if ($insolvent > 0) {
            return back()->with('error', "Reconciliation complete — {$insolvent} asset(s) INSOLVENT.");
        }

        return back()->with('success', 'Reconciliation complete — all assets solvent.');
    }

    private function guardAccess(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-treasury') || $admin->hasRole('super-admin'), 403);
    }
}
