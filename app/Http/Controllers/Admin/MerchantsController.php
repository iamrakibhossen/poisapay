<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Merchant\SetMerchantStatusAction;
use App\Enums\MerchantStatus;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin merchants console (DollarHub structure — controller + Blade, not
 * Livewire). Approve/reactivate/suspend transition status via
 * {@see SetMerchantStatusAction}; a per-merchant fee override is stored in bps.
 */
class MerchantsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('view-merchants') || auth('admin')->user()?->hasRole('super-admin'), 403);

        $search = (string) $request->query('search', '');
        $status = (string) $request->query('status', 'all');

        $merchants = Merchant::query()
            ->with(['user', 'settlementAsset'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('business_name', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%')
                ->orWhereHas('user', fn ($u) => $u
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'))))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        // Per-merchant invoice aggregates (paid volume + counts), keyed by the
        // merchant's user_id since MerchantInvoice links via merchant_id = user_id.
        $userIds = $merchants->pluck('user_id')->all();

        $paidAgg = MerchantInvoice::query()
            ->selectRaw('merchant_id, COUNT(*) as cnt, COALESCE(SUM(amount), 0) as gross')
            ->where('status', 'paid')
            ->whereIn('merchant_id', $userIds)
            ->groupBy('merchant_id')
            ->get()
            ->keyBy('merchant_id');

        $totalCounts = MerchantInvoice::query()
            ->selectRaw('merchant_id, COUNT(*) as cnt')
            ->whereIn('merchant_id', $userIds)
            ->groupBy('merchant_id')
            ->get()
            ->keyBy('merchant_id');

        // Recent invoices per listed merchant (user_id keyed) for the Alpine detail modal.
        $invoices = MerchantInvoice::with('asset')
            ->whereIn('merchant_id', $userIds)
            ->latest()
            ->get()
            ->groupBy('merchant_id');

        return view('admin.merchants', [
            'merchants' => $merchants,
            'paidAgg' => $paidAgg,
            'totalCounts' => $totalCounts,
            'invoices' => $invoices,
            'search' => $search,
            'status' => $status,
            'stats' => [
                'total' => Merchant::count(),
                'active' => Merchant::where('status', MerchantStatus::Active->value)->count(),
                'pending' => Merchant::where('status', MerchantStatus::Pending->value)->count(),
                'suspended' => Merchant::where('status', MerchantStatus::Suspended->value)->count(),
                'volume' => (int) MerchantInvoice::where('status', 'paid')->sum('amount'),
            ],
        ]);
    }

    public function approve(string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('manage-merchants') || auth('admin')->user()?->hasRole('super-admin'), 403);

        try {
            $merchant = Merchant::findOrFail($id);
            app(SetMerchantStatusAction::class)->execute($merchant, MerchantStatus::Active);

            return back()->with('success', 'Merchant approved and activated.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not approve the merchant: '.$e->getMessage());
        }
    }

    public function reactivate(string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('manage-merchants') || auth('admin')->user()?->hasRole('super-admin'), 403);

        try {
            $merchant = Merchant::findOrFail($id);
            app(SetMerchantStatusAction::class)->execute($merchant, MerchantStatus::Active);

            return back()->with('success', 'Merchant reactivated.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reactivate the merchant: '.$e->getMessage());
        }
    }

    public function suspend(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('manage-merchants') || auth('admin')->user()?->hasRole('super-admin'), 403);

        $data = $request->validate([
            'suspendReason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $merchant = Merchant::findOrFail($id);
            app(SetMerchantStatusAction::class)->execute($merchant, MerchantStatus::Suspended, $data['suspendReason']);

            return back()->with('success', 'Merchant suspended.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not suspend the merchant: '.$e->getMessage());
        }
    }

    public function saveFee(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('manage-merchants') || auth('admin')->user()?->hasRole('super-admin'), 403);

        $data = $request->validate([
            'feeInput' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        try {
            $merchant = Merchant::findOrFail($id);
            $fee = $data['feeInput'] ?? null;
            $merchant->update([
                'fee_bps' => ($fee === null || $fee === '') ? null : (int) $fee,
            ]);

            return back()->with('success', 'Processing fee updated.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not update the fee: '.$e->getMessage());
        }
    }
}
