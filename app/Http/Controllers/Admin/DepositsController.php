<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Deposit\CreditManualDepositAction;
use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Models\Deposit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin deposits queue (DollarHub structure — controller + Blade, not Livewire).
 * Money-critical: approving a manual deposit credits treasury:pending ->
 * user:available via {@see CreditManualDepositAction}; rejecting orphans it.
 */
class DepositsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()->can('view-deposits') || auth('admin')->user()->hasRole('super-admin'), 403);

        $status = (string) $request->query('status', 'all');
        $search = (string) $request->query('search', '');

        $deposits = Deposit::with('user', 'asset', 'depositMethod')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereHas(
                'user',
                fn ($u) => $u->where('email', 'like', '%'.$search.'%')
            ))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.deposits', [
            'deposits' => $deposits,
            'status' => $status,
            'search' => $search,
            'tabs' => [
                'all' => Deposit::count(),
                'detected' => Deposit::where('status', DepositStatus::Detected->value)->count(),
                'confirming' => Deposit::where('status', DepositStatus::Confirming->value)->count(),
                'credited' => Deposit::where('status', DepositStatus::Credited->value)->count(),
            ],
        ]);
    }

    public function approve(string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('view-deposits') || auth('admin')->user()?->hasRole('super-admin'), 403);

        try {
            $deposit = Deposit::findOrFail($id);
            app(CreditManualDepositAction::class)->execute($deposit, auth('admin')->user());

            return back()->with('success', 'Deposit approved and credited.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not approve the deposit: '.$e->getMessage());
        }
    }

    public function reject(string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('view-deposits') || auth('admin')->user()?->hasRole('super-admin'), 403);

        try {
            $deposit = Deposit::findOrFail($id);
            app(CreditManualDepositAction::class)->reject($deposit, auth('admin')->user());

            return back()->with('success', 'Deposit rejected.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reject the deposit: '.$e->getMessage());
        }
    }
}
