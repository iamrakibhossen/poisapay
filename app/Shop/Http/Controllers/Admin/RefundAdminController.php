<?php

declare(strict_types=1);

namespace App\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Shop\Actions\Refund\ResolveRefundRequest;
use App\Shop\Enums\RefundRequestStatus;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\RefundRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Operator refund review (DollarHub structure — controller + Blade). Shows the
 * refund queue (escalated requests by default) and lets an operator approve
 * (posts the ledger refund) or decline. Reuses the marketplace seller permissions.
 */
class RefundAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView();

        $status = (string) $request->query('status', RefundRequestStatus::Escalated->value);

        $requests = RefundRequest::query()
            ->with(['order.asset', 'buyer', 'seller.user'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.sell.refunds.index', [
            'requests' => $requests,
            'status' => $status,
            'escalatedCount' => RefundRequest::where('status', RefundRequestStatus::Escalated->value)->count(),
        ]);
    }

    public function show(RefundRequest $refundRequest): View
    {
        $this->authorizeView();

        return view('admin.sell.refunds.show', [
            'request' => $refundRequest->load(['order.asset', 'buyer', 'seller.user']),
        ]);
    }

    public function approve(Request $request, ResolveRefundRequest $action, RefundRequest $refundRequest): RedirectResponse
    {
        return $this->resolve($request, $action, $refundRequest, approve: true);
    }

    public function reject(Request $request, ResolveRefundRequest $action, RefundRequest $refundRequest): RedirectResponse
    {
        return $this->resolve($request, $action, $refundRequest, approve: false);
    }

    private function resolve(Request $request, ResolveRefundRequest $action, RefundRequest $refundRequest, bool $approve): RedirectResponse
    {
        $this->authorizeManage();

        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        try {
            $approve
                ? $action->approve($refundRequest, auth('admin')->user(), $note)
                : $action->reject($refundRequest, auth('admin')->user(), $note);
        } catch (ShopException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return redirect()->route('admin.sell-refunds.show', $refundRequest->id)
            ->with('success', $approve ? __('Refund approved — the buyer has been repaid.') : __('Refund request declined.'));
    }

    private function authorizeView(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin && ($admin->can('view-sellers') || $admin->hasRole('super-admin')), 403);
    }

    private function authorizeManage(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin && ($admin->can('manage-sellers') || $admin->hasRole('super-admin')), 403);
    }
}
