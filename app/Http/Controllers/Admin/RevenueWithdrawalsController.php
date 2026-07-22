<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Revenue\ProcessRevenueWithdrawalAction;
use App\Domain\Revenue\RevenueService;
use App\Enums\RevenueWithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\RevenueWithdrawal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin revenue withdrawals queue (DollarHub structure — controller + Blade, not
 * Livewire). Money-critical: approval posts the balanced ledger move out of the
 * revenue wallet and queues the on-chain broadcast (§Finance). Password-confirmed.
 */
class RevenueWithdrawalsController extends Controller
{
    public function index(Request $request, RevenueService $revenue): View
    {
        abort_unless(auth('admin')->user()?->can('view-revenue') || auth('admin')->user()?->hasRole('super-admin'), 403);

        $status = (string) $request->query('status', 'all');

        $withdrawals = RevenueWithdrawal::with(['asset', 'creator', 'approver'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $asset = Asset::where('symbol', 'USDT')->first() ?? Asset::where('kind', 'crypto')->first();

        return view('admin.revenue-withdrawals', [
            'withdrawals' => $withdrawals,
            'status' => $status,
            'canApprove' => auth('admin')->user()?->can('approve-revenue-withdrawal') || auth('admin')->user()?->hasRole('super-admin'),
            'tabs' => [
                'all' => RevenueWithdrawal::count(),
                'pending' => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Pending->value)->count(),
                'approved' => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Approved->value)->count(),
                'processing' => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Processing->value)->count(),
                'completed' => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Completed->value)->count(),
                'failed' => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Failed->value)->count(),
            ],
            'pendingCount' => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Pending->value)->count(),
            'completedCount' => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Completed->value)->count(),
            'totalWithdrawn' => $asset ? $revenue->withdrawn($asset)->format() : '—',
        ]);
    }

    public function approve(Request $request, string $id, ProcessRevenueWithdrawalAction $action): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('approve-revenue-withdrawal') || auth('admin')->user()?->hasRole('super-admin'), 403);

        $request->validate(['password' => 'required|string']);

        if (! Hash::check($request->input('password'), auth('admin')->user()->password)) {
            return back()->withErrors(['password' => 'The password is incorrect.']);
        }

        try {
            $w = RevenueWithdrawal::findOrFail($id);

            if ($w->status !== RevenueWithdrawalStatus::Pending) {
                return back()->with('error', 'This withdrawal can no longer be approved.');
            }

            $action->approve($w, auth('admin')->user());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Approved — ledger posted and broadcast queued.');
    }
}
