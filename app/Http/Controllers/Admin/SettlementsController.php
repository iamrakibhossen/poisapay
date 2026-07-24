<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RevenueWithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\RevenueWithdrawal;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settlements — "Which revenue payouts are pending approval or in flight?"
 * Read-only queue of RevenueWithdrawals with status tabs. Approval reuses the
 * existing revenue-withdrawals.approve action (no new money logic here).
 */
class SettlementsController extends Controller
{
    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-revenue') || $admin->hasRole('super-admin'), 403);

        $status = (string) $request->query('status', 'all');

        $settlements = RevenueWithdrawal::with('asset', 'creator', 'approver')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.settlements', [
            'settlements' => $settlements,
            'status' => $status,
            'tabs' => [
                'all' => RevenueWithdrawal::count(),
                RevenueWithdrawalStatus::Pending->value => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Pending->value)->count(),
                RevenueWithdrawalStatus::Processing->value => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Processing->value)->count(),
                RevenueWithdrawalStatus::Completed->value => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Completed->value)->count(),
                RevenueWithdrawalStatus::Failed->value => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Failed->value)->count(),
            ],
        ]);
    }
}
