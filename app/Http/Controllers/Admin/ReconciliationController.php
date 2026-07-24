<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReconciliationRun;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reconciliation — "Does the ledger reconcile against on-chain/treasury over time?"
 * Read-only run history (ledger treasury vs liability, drift, solvency). The
 * current-state snapshot lives on Company Funds (admin.treasury); this is the
 * audit trail. Running a reconciliation reuses the existing treasury.reconcile POST.
 */
class ReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-treasury') || $admin->hasRole('super-admin'), 403);

        $status = (string) $request->query('status', 'all');

        $runs = ReconciliationRun::with('asset')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.reconciliation', [
            'runs' => $runs,
            'status' => $status,
            'tabs' => [
                'all' => ReconciliationRun::count(),
                'ok' => ReconciliationRun::where('status', 'ok')->count(),
                'drift' => ReconciliationRun::where('status', 'drift')->count(),
                'insolvent' => ReconciliationRun::where('status', 'insolvent')->count(),
            ],
        ]);
    }
}
