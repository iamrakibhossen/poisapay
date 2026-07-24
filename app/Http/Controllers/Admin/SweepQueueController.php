<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SweepStatus;
use App\Http\Controllers\Controller;
use App\Models\Sweep;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sweep Queue — "Which custody sweeps are pending or stuck?" Read-only list of
 * deposit-address → treasury sweeps with status tabs. The sweep pipeline itself
 * is untouched; this only surfaces it.
 */
class SweepQueueController extends Controller
{
    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-treasury') || $admin->hasRole('super-admin'), 403);

        $status = (string) $request->query('status', 'all');

        $sweeps = Sweep::with('asset', 'onchainTx')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.sweeps', [
            'sweeps' => $sweeps,
            'status' => $status,
            'tabs' => [
                'all' => Sweep::count(),
                'pending' => Sweep::where('status', SweepStatus::Pending->value)->count(),
                'broadcast' => Sweep::where('status', SweepStatus::Broadcast->value)->count(),
                'swept' => Sweep::where('status', SweepStatus::Swept->value)->count(),
                'failed' => Sweep::where('status', SweepStatus::Failed->value)->count(),
            ],
        ]);
    }
}
