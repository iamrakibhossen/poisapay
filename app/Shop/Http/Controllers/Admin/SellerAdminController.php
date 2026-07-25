<?php

declare(strict_types=1);

namespace App\Shop\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Seller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin sellers console (DollarHub structure — controller + Blade). Lists sellers
 * and lets an operator move a seller between plan tiers (free/pro/business), which
 * drives their commission rate, or set an explicit per-seller commission override.
 */
class SellerAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView();

        $search = (string) $request->query('search', '');
        $status = (string) $request->query('status', 'all');
        $plan = (string) $request->query('plan', 'all');

        $sellers = Seller::query()
            ->with(['user', 'settlementAsset'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when(in_array($plan, Seller::PLANS, true), fn ($q) => $q->where('plan', $plan))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('brand_name', 'like', '%'.$search.'%')
                ->orWhereHas('user', fn ($u) => $u
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'))))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.sell.sellers.index', [
            'sellers' => $sellers,
            'search' => $search,
            'status' => $status,
            'plan' => $plan,
            'stats' => [
                'total' => Seller::count(),
                'free' => Seller::where('plan', 'free')->count(),
                'pro' => Seller::where('plan', 'pro')->count(),
                'business' => Seller::where('plan', 'business')->count(),
                'approved' => Seller::where('status', SellerStatus::Approved->value)->count(),
            ],
        ]);
    }

    public function updatePlan(Request $request, Seller $seller): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'plan' => ['required', 'string', 'in:'.implode(',', Seller::PLANS)],
            // Blank = clear the override and fall back to the plan's rate.
            'commission_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $override = $data['commission_bps'] ?? null;

        $seller->update([
            'plan' => $data['plan'],
            'commission_bps' => ($override === null || $override === '') ? null : (int) $override,
        ]);

        ActivityLogger::log('sell.seller.plan_updated', $seller, [
            'plan' => $seller->plan,
            'commission_bps' => $seller->commission_bps,
            'effective_bps' => $seller->commissionBps(),
        ]);

        return back()->with('success', __('Seller plan updated.'));
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
