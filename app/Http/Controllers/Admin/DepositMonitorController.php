<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\OnchainTx;
use Illuminate\View\View;

/**
 * Deposit Monitor — "Are on-chain deposits being detected and confirming?"
 * Read-only: in-flight deposits (detected/confirming) with their confirmation
 * progress, plus the most recent observed on-chain transactions.
 */
class DepositMonitorController extends Controller
{
    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-treasury') || $admin->hasRole('super-admin'), 403);

        $inflight = Deposit::with('user', 'asset', 'onchainTx')
            ->whereIn('status', [DepositStatus::Detected->value, DepositStatus::Confirming->value])
            ->latest()
            ->paginate(20);

        return view('admin.deposit-monitor', [
            'inflight' => $inflight,
            'recentTxs' => OnchainTx::with('chain', 'asset')->where('direction', 'in')->latest()->limit(15)->get(),
            'stats' => [
                'detected' => Deposit::where('status', DepositStatus::Detected->value)->count(),
                'confirming' => Deposit::where('status', DepositStatus::Confirming->value)->count(),
                'creditedToday' => Deposit::where('status', DepositStatus::Credited->value)->whereDate('credited_at', today())->count(),
                'orphaned' => Deposit::where('status', DepositStatus::Orphaned->value)->count(),
            ],
        ]);
    }
}
