<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\LedgerService;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin withdrawals queue (DollarHub structure — controller + Blade, not Livewire).
 * Money-critical: approving hands the withdrawal to the signer; cancelling releases
 * the ledger lock (reserve-before-sign, §6.3).
 */
class WithdrawalsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()->can('view-withdrawals') || auth('admin')->user()->hasRole('super-admin'), 403);

        $status = (string) $request->query('status', 'review');

        $withdrawals = Withdrawal::with('user', 'asset')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.withdrawals', [
            'withdrawals' => $withdrawals,
            'status' => $status,
            'tabs' => [
                'review' => Withdrawal::where('status', WithdrawalStatus::Review->value)->count(),
                'approved' => Withdrawal::where('status', WithdrawalStatus::Approved->value)->count(),
                'completed' => Withdrawal::where('status', WithdrawalStatus::Completed->value)->count(),
                'all' => Withdrawal::count(),
            ],
            'canApprove' => auth('admin')->user()->can('approve-withdrawals') || auth('admin')->user()->hasRole('super-admin'),
        ]);
    }

    public function approve(string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('approve-withdrawals') || auth('admin')->user()->hasRole('super-admin'), 403);

        $w = Withdrawal::findOrFail($id);
        if (! $w->status->isReversibleLock()) {
            return back()->with('error', 'This withdrawal can no longer be approved.');
        }

        $w->update([
            'status' => WithdrawalStatus::Approved,
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ]);

        ActivityLogger::log('withdrawal.approved', $w);

        // Handed to the isolated Withdrawal Signer (§3.2) for signing + broadcast.
        return back()->with('success', 'Approved — queued for signing.');
    }

    public function cancel(string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('approve-withdrawals') || auth('admin')->user()->hasRole('super-admin'), 403);

        $w = Withdrawal::with('asset')->findOrFail($id);
        if (! $w->status->isReversibleLock()) {
            return back()->with('error', 'Funds already settled — cannot cancel.');
        }

        DB::transaction(function () use ($w) {
            // Release the reserve: locked -> available (§6.3 step 7).
            $total = Money::ofBase($w->amount, $w->asset->decimals, $w->asset->symbol)
                ->plus(Money::ofBase($w->fee, $w->asset->decimals, $w->asset->symbol));

            try {
                app(LedgerService::class)->unlock(
                    $w->user_id, $w->asset_id, $total, "withdrawal:unlock:{$w->id}", 'withdrawal.cancel',
                );
            } catch (\Throwable $e) {
                // If the lock was never posted, there is nothing to release.
            }

            $w->update(['status' => WithdrawalStatus::Cancelled, 'failure_reason' => 'Cancelled by operator']);
        });

        ActivityLogger::log('withdrawal.cancelled', $w);

        return back()->with('success', 'Withdrawal cancelled and funds released.');
    }
}
