<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\P2p\AssignDisputeAction;
use App\Domain\P2p\ResolveDisputeAction;
use App\Enums\LedgerAccountType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\LedgerAccount;
use App\Models\P2pDispute;
use App\Models\P2pDisputeEvidence;
use App\Models\P2pOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Operator P2P console — read-only order monitoring plus dispute adjudication
 * (force release / force cancel). Escrow only ever settles via the ledger
 * through {@see ResolveDisputeAction}.
 */
class P2pController extends Controller
{
    public function orders(Request $request): View
    {
        $this->authorizeP2p('view-p2p');

        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $orders = P2pOrder::query()
            ->with(['buyer', 'seller', 'asset'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where('ref', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.p2p-orders', [
            'orders' => $orders,
            'status' => $status,
            'search' => $search,
            'stats' => [
                'open' => P2pOrder::whereIn('status', ['waiting_payment', 'buyer_paid', 'releasing'])->count(),
                'disputed' => P2pOrder::where('status', 'disputed')->count(),
                'completed' => P2pOrder::whereIn('status', ['completed', 'force_released'])->count(),
                'fee_income' => $this->feeIncome(),
            ],
        ]);
    }

    public function disputes(Request $request): View
    {
        $this->authorizeP2p('view-p2p');

        $disputes = P2pDispute::with(['order.buyer', 'order.seller', 'order.asset', 'opener'])
            ->orderByRaw("case when status in ('open','under_review') then 0 else 1 end")
            ->latest()
            ->paginate(25);

        return view('admin.p2p-disputes', ['disputes' => $disputes]);
    }

    public function dispute(Request $request, P2pDispute $dispute): View
    {
        $this->authorizeP2p('view-p2p');

        $dispute->load([
            'order.buyer', 'order.seller', 'order.asset',
            'order.events', 'order.messages', 'evidence', 'assignedAdmin',
        ]);

        return view('admin.p2p-dispute', ['dispute' => $dispute]);
    }

    public function assign(Request $request, P2pDispute $dispute, AssignDisputeAction $action): RedirectResponse
    {
        $this->authorizeP2p('manage-p2p');

        try {
            $action->execute($dispute, Auth::guard('admin')->user());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Dispute assigned to you and marked under review.');
    }

    public function disputeEvidence(Request $request, P2pDisputeEvidence $evidence): StreamedResponse
    {
        $this->authorizeP2p('view-p2p');

        abort_unless(Storage::disk('local')->exists($evidence->path), 404);

        return Storage::disk('local')->download($evidence->path);
    }

    public function resolve(Request $request, P2pDispute $dispute, ResolveDisputeAction $action): RedirectResponse
    {
        $this->authorizeP2p('manage-p2p');

        $data = $request->validate([
            'winner' => ['required', 'in:buyer,seller'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $action->execute($dispute, Auth::guard('admin')->user(), $data['winner'], $data['note'] ?? null);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Dispute resolved in favour of the '.$data['winner'].'.');
    }

    private function feeIncome(): string
    {
        $usdt = Asset::where('symbol', 'USDT')->first();
        if (! $usdt) {
            return '—';
        }

        $account = LedgerAccount::where('type', LedgerAccountType::P2pFeeIncome->value)
            ->where('asset_id', $usdt->id)
            ->whereNull('user_id')
            ->first();

        return $account ? $account->money()->format() : '0 USDT';
    }

    private function authorizeP2p(string $permission): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin && ($admin->can($permission) || $admin->hasRole('super-admin')), 403);
    }
}
