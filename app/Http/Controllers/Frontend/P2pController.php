<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Audit\ActivityLogger;
use App\Domain\P2p\AddDisputeEvidenceAction;
use App\Domain\P2p\CancelOrderAction;
use App\Domain\P2p\ConfirmReleaseAction;
use App\Domain\P2p\CreateAdAction;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Domain\P2p\OpenDisputeAction;
use App\Domain\P2p\UpdateAdAction;
use App\Enums\P2pAdStatus;
use App\Enums\P2pAdType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureP2pEnabled;
use App\Models\Asset;
use App\Models\P2pAd;
use App\Models\P2pDisputeEvidence;
use App\Models\P2pMerchantProfile;
use App\Models\P2pOrder;
use App\Models\P2pPaymentMethod;
use App\Models\P2pUserPaymentMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Consumer P2P marketplace — server-rendered Blade + form-POST mutations that
 * delegate to the domain actions and redirect back with a flash. No business
 * logic here. Gated by {@see EnsureP2pEnabled}.
 */
class P2pController extends Controller
{
    /** Marketplace: browse ads on the opposite side of what you want to do. */
    public function index(Request $request): View
    {
        $want = $request->query('side', 'buy') === 'sell' ? 'sell' : 'buy';
        $adSide = $want === 'buy' ? P2pAdType::Sell : P2pAdType::Buy;

        $ads = P2pAd::query()
            ->with(['user', 'asset', 'paymentMethods'])
            ->where('side', $adSide->value)
            ->where('status', P2pAdStatus::Active->value)
            ->where('user_id', '!=', $request->user()->getKey())
            ->where('available_amount', '>', '0')
            ->whereNotIn('user_id', P2pMerchantProfile::where('vacation_mode', true)->select('user_id'))
            ->orderByDesc('priority')
            ->orderBy('fixed_price', $adSide === P2pAdType::Sell ? 'asc' : 'desc')
            ->paginate(15)
            ->withQueryString();

        // Reputation, keyed by advertiser — loaded separately to avoid touching the User model.
        $profiles = P2pMerchantProfile::whereIn('user_id', $ads->pluck('user_id')->unique())
            ->get()->keyBy('user_id');

        return view('frontend.p2p.marketplace', [
            'want' => $want,
            'ads' => $ads,
            'profiles' => $profiles,
            'methods' => P2pPaymentMethod::where('is_active', true)->orderBy('sort')->get(),
        ]);
    }

    /** Ad status buckets that power the quick-filter tabs. */
    private const AD_TABS = [
        'active' => ['active'],
        'paused' => ['paused'],
        'closed' => ['disabled', 'archived', 'draft'],
    ];

    public function myAds(Request $request): View
    {
        $uid = $request->user()->getKey();
        $tab = array_key_exists($request->query('tab'), self::AD_TABS) ? $request->query('tab') : 'all';

        $ads = P2pAd::with(['asset', 'paymentMethods'])
            ->where('user_id', $uid)
            ->when($tab !== 'all', fn ($q) => $q->whereIn('status', self::AD_TABS[$tab]))
            ->orderByDesc('priority')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $byStatus = P2pAd::where('user_id', $uid)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $counts = ['all' => (int) $byStatus->sum()];
        foreach (self::AD_TABS as $key => $statuses) {
            $counts[$key] = (int) collect($statuses)->sum(fn ($s) => $byStatus[$s] ?? 0);
        }

        return view('frontend.p2p.ads', [
            'ads' => $ads,
            'profile' => $this->profileFor($uid),
            'tab' => $tab,
            'counts' => $counts,
        ]);
    }

    public function createAd(Request $request): View
    {
        return view('frontend.p2p.ad-create', [
            'methods' => $this->userPaymentMethods($request->user()),
            'asset' => Asset::where('symbol', 'USDT')->where('is_active', true)->first(),
        ]);
    }

    /**
     * Payment method types the user has a saved payout account for — the only
     * rails they may advertise, so a buyer always has somewhere to pay. When
     * editing, the ad's existing methods are unioned in so nothing is dropped.
     *
     * @param  array<int, string>  $includeIds
     */
    private function userPaymentMethods(User $user, array $includeIds = [])
    {
        $ids = P2pUserPaymentMethod::where('user_id', $user->getKey())
            ->where('is_active', true)->pluck('payment_method_id')->all();
        $ids = array_values(array_unique(array_merge($ids, $includeIds)));

        return P2pPaymentMethod::whereIn('id', $ids)->where('is_active', true)->orderBy('sort')->get();
    }

    /** @return array<int, string> Method-type ids the user has an active account for. */
    private function ownedMethodIds(User $user): array
    {
        return P2pUserPaymentMethod::where('user_id', $user->getKey())
            ->where('is_active', true)->pluck('payment_method_id')->unique()->all();
    }

    public function storeAd(Request $request, CreateAdAction $action): RedirectResponse
    {
        $data = $request->validate([
            'side' => ['required', 'in:buy,sell'],
            'price_type' => ['required', 'in:fixed,floating'],
            'fixed_price' => ['nullable', 'numeric', 'gt:0'],
            'margin_bps' => ['nullable', 'integer'],
            'min_order' => ['required', 'numeric', 'gt:0'],
            'max_order' => ['required', 'numeric', 'gte:min_order'],
            'total_amount' => ['required', 'numeric', 'gt:0'],
            'payment_window_min' => ['required', 'integer', 'min:5', 'max:180'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'payment_method_ids' => ['required', 'array', 'min:1'],
            'payment_method_ids.*' => ['string', 'exists:p2p_payment_methods,id'],
        ]);

        $owned = $this->ownedMethodIds($request->user());
        if (empty($owned)) {
            return back()->withInput()->with('error', 'Add a payment account before posting an ad — buyers pay into your saved accounts.');
        }
        $data['payment_method_ids'] = array_values(array_intersect($data['payment_method_ids'], $owned));
        if (empty($data['payment_method_ids'])) {
            return back()->withInput()->withErrors(['payment_method_ids' => 'Select at least one method you have a saved account for.']);
        }

        $asset = Asset::where('symbol', 'USDT')->where('is_active', true)->firstOrFail();

        try {
            $action->execute($request->user(), [
                'side' => $data['side'],
                'asset_id' => $asset->id,
                'decimals' => $asset->decimals,
                'symbol' => $asset->symbol,
                'fiat_currency' => 'BDT',
                'price_type' => $data['price_type'],
                'fixed_price' => $data['fixed_price'] ?? null,
                'margin_bps' => $data['margin_bps'] ?? null,
                'min_order' => $data['min_order'],
                'max_order' => $data['max_order'],
                'total_amount' => Money::ofDecimal($data['total_amount'], $asset->decimals, $asset->symbol)->baseString(),
                'payment_window_min' => $data['payment_window_min'],
                'terms' => $data['terms'] ?? null,
                'payment_method_ids' => $data['payment_method_ids'],
            ]);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('p2p.ads')->with('success', 'Your ad is live.');
    }

    public function toggleAd(Request $request, P2pAd $ad): RedirectResponse
    {
        abort_unless($ad->user_id === $request->user()->getKey(), 403);

        $next = $ad->status === P2pAdStatus::Active ? P2pAdStatus::Paused : P2pAdStatus::Active;
        $ad->update(['status' => $next]);
        ActivityLogger::log('p2p.ad.toggled', $ad, ['status' => $next->value], actor: $request->user());

        return back()->with('success', 'Ad '.($next === P2pAdStatus::Active ? 'resumed' : 'paused').'.');
    }

    public function editAd(Request $request, P2pAd $ad): View
    {
        abort_unless($ad->user_id === $request->user()->getKey(), 403);

        $ad->load(['paymentMethods', 'asset']);

        return view('frontend.p2p.ad-create', [
            'ad' => $ad,
            'methods' => $this->userPaymentMethods($request->user(), $ad->paymentMethods->pluck('id')->all()),
            'asset' => $ad->asset,
        ]);
    }

    public function updateAd(Request $request, P2pAd $ad, UpdateAdAction $action): RedirectResponse
    {
        abort_unless($ad->user_id === $request->user()->getKey(), 403);

        $data = $request->validate([
            'price_type' => ['required', 'in:fixed,floating'],
            'fixed_price' => ['nullable', 'numeric', 'gt:0'],
            'margin_bps' => ['nullable', 'integer'],
            'min_order' => ['required', 'numeric', 'gt:0'],
            'max_order' => ['required', 'numeric', 'gte:min_order'],
            'total_amount' => ['required', 'numeric', 'gt:0'],
            'payment_window_min' => ['required', 'integer', 'min:5', 'max:180'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'payment_method_ids' => ['required', 'array', 'min:1'],
            'payment_method_ids.*' => ['string', 'exists:p2p_payment_methods,id'],
        ]);

        // Keep any methods already on the ad, plus any the user now has an account for.
        $allowed = array_values(array_unique(array_merge($this->ownedMethodIds($request->user()), $ad->paymentMethods->pluck('id')->all())));
        $data['payment_method_ids'] = array_values(array_intersect($data['payment_method_ids'], $allowed));
        if (empty($data['payment_method_ids'])) {
            return back()->withInput()->withErrors(['payment_method_ids' => 'Select at least one method you have a saved account for.']);
        }

        $asset = $ad->asset;

        try {
            $action->execute($request->user(), $ad, [
                'decimals' => $asset->decimals,
                'symbol' => $asset->symbol,
                'price_type' => $data['price_type'],
                'fixed_price' => $data['fixed_price'] ?? null,
                'margin_bps' => $data['margin_bps'] ?? null,
                'min_order' => $data['min_order'],
                'max_order' => $data['max_order'],
                'total_amount' => Money::ofDecimal($data['total_amount'], $asset->decimals, $asset->symbol)->baseString(),
                'payment_window_min' => $data['payment_window_min'],
                'terms' => $data['terms'] ?? null,
                'payment_method_ids' => $data['payment_method_ids'],
            ]);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('p2p.ads')->with('success', 'Your ad has been updated.');
    }

    /** Order status buckets that power the quick-filter tabs. */
    private const ORDER_TABS = [
        'active' => ['waiting_payment', 'buyer_paid', 'releasing'],
        'completed' => ['completed', 'force_released'],
        'cancelled' => ['cancelled', 'expired', 'force_cancelled', 'refunded'],
        'disputed' => ['disputed'],
    ];

    public function orders(Request $request): View
    {
        $me = $request->user()->getKey();
        $mine = fn ($q) => $q->where('buyer_id', $me)->orWhere('seller_id', $me);

        $tab = array_key_exists($request->query('tab'), self::ORDER_TABS) ? $request->query('tab') : 'all';

        $orders = P2pOrder::with(['asset', 'ad', 'buyer', 'seller'])
            ->where($mine)
            ->when($request->query('role') === 'buying', fn ($q) => $q->where('buyer_id', $me))
            ->when($request->query('role') === 'selling', fn ($q) => $q->where('seller_id', $me))
            ->when($tab !== 'all', fn ($q) => $q->whereIn('status', self::ORDER_TABS[$tab]))
            ->when($request->filled('search'), fn ($q) => $q->where('ref', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // One grouped query → per-status counts, summed into the tab buckets below.
        $byStatus = P2pOrder::where($mine)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $counts = ['all' => (int) $byStatus->sum()];
        foreach (self::ORDER_TABS as $key => $statuses) {
            $counts[$key] = (int) collect($statuses)->sum(fn ($s) => $byStatus[$s] ?? 0);
        }

        return view('frontend.p2p.orders', [
            'orders' => $orders,
            'me' => $me,
            'tab' => $tab,
            'counts' => $counts,
        ]);
    }

    public function createOrder(Request $request, CreateOrderAction $action): RedirectResponse
    {
        $data = $request->validate([
            'ad_id' => ['required', 'string', 'exists:p2p_ads,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['nullable', 'string', 'exists:p2p_payment_methods,id'],
        ]);

        $ad = P2pAd::with('asset')->findOrFail($data['ad_id']);

        try {
            $order = $action->execute(
                $request->user(),
                $ad,
                Money::ofDecimal($data['amount'], $ad->asset->decimals, $ad->asset->symbol),
                $data['payment_method_id'] ?? null,
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('p2p.order', $order)->with('success', 'Order opened — escrow is locked.');
    }

    public function order(Request $request, P2pOrder $order): View
    {
        $this->assertParty($request, $order);
        $order->load(['ad', 'buyer', 'seller', 'asset', 'escrow', 'paymentMethod', 'dispute.evidence']);

        // The seller's payout accounts for this order's rail — surfaced to the buyer
        // (who pays the fiat) once an order is open.
        $payToAccounts = $order->payment_method_id
            ? P2pUserPaymentMethod::with('method')
                ->where('user_id', $order->seller_id)
                ->where('payment_method_id', $order->payment_method_id)
                ->where('is_active', true)->get()
            : collect();

        return view('frontend.p2p.order', [
            'order' => $order,
            'me' => $request->user()->getKey(),
            'isBuyer' => $order->buyer_id === $request->user()->getKey(),
            'payToAccounts' => $payToAccounts,
        ]);
    }

    public function paymentMethods(Request $request): View
    {
        $methods = P2pPaymentMethod::where('is_active', true)->orderBy('sort')->get();

        return view('frontend.p2p.payment-methods', [
            'accounts' => P2pUserPaymentMethod::with('method')
                ->where('user_id', $request->user()->getKey())->latest()->get(),
            'methods' => $methods,
            // key => field schema, for the Alpine-driven dynamic form.
            'methodFields' => $methods->mapWithKeys(fn ($m) => [$m->id => $this->methodFields($m)]),
        ]);
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'string', 'exists:p2p_payment_methods,id'],
            'label' => ['nullable', 'string', 'max:60'],
            'account' => ['required', 'array'],
            'account.*' => ['nullable', 'string', 'max:160'],
        ]);

        $method = P2pPaymentMethod::findOrFail($data['payment_method_id']);
        $schema = $this->methodFields($method);

        // Validate + collect only the fields this method declares.
        $account = [];
        foreach ($schema as $f) {
            $val = trim((string) ($data['account'][$f['key']] ?? ''));
            if (($f['required'] ?? false) && $val === '') {
                return back()->withInput()->withErrors(["account.{$f['key']}" => $f['label'].' is required.']);
            }
            if ($val !== '') {
                $account[$f['key']] = $val;
            }
        }

        if (empty($account)) {
            return back()->withInput()->with('error', 'Fill in your account details.');
        }

        P2pUserPaymentMethod::create([
            'user_id' => $request->user()->getKey(),
            'payment_method_id' => $method->id,
            'label' => $data['label'] ?? null,
            'account' => $account,
            'is_active' => true,
        ]);

        return back()->with('success', 'Payment account added.');
    }

    /**
     * The field schema for a method, falling back to a generic name+number pair
     * if an admin hasn't configured one yet.
     *
     * @return array<int, array{key: string, label: string, required: bool}>
     */
    private function methodFields(P2pPaymentMethod $method): array
    {
        $fields = $method->fields ?: [];

        return ! empty($fields) ? $fields : [
            ['key' => 'account_name', 'label' => 'Account name', 'required' => true],
            ['key' => 'account_number', 'label' => 'Account number', 'required' => true],
        ];
    }

    public function destroyPaymentMethod(Request $request, P2pUserPaymentMethod $method): RedirectResponse
    {
        abort_unless($method->user_id === $request->user()->getKey(), 403);

        $method->delete();

        return back()->with('success', 'Payment account removed.');
    }

    public function markPaid(Request $request, P2pOrder $order, MarkBuyerPaidAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->execute($order, $request->user()), $order, 'Marked as paid.');
    }

    public function merchant(Request $request, User $user): View
    {
        $profile = $this->profileFor($user->getKey());

        $ads = P2pAd::with(['asset', 'paymentMethods'])
            ->where('user_id', $user->getKey())
            ->where('status', P2pAdStatus::Active->value)
            ->where('available_amount', '>', '0')
            ->orderByDesc('priority')
            ->get();

        return view('frontend.p2p.merchant', [
            'trader' => $user,
            'profile' => $profile,
            'ads' => $ads,
            'isSelf' => $user->getKey() === $request->user()->getKey(),
        ]);
    }

    public function toggleOnline(Request $request): RedirectResponse
    {
        $profile = $this->profileFor($request->user()->getKey());
        $profile->update(['is_online' => ! $profile->is_online]);

        return back()->with('success', $profile->is_online ? 'You are now online.' : 'You are now offline.');
    }

    public function toggleVacation(Request $request): RedirectResponse
    {
        $profile = $this->profileFor($request->user()->getKey());
        $profile->update(['vacation_mode' => ! $profile->vacation_mode]);

        return back()->with('success', $profile->vacation_mode
            ? 'Vacation mode on — your ads are hidden from the marketplace.'
            : 'Vacation mode off — your ads are visible again.');
    }

    private function profileFor(string $userId): P2pMerchantProfile
    {
        return P2pMerchantProfile::firstOrCreate(
            ['user_id' => $userId],
            ['trade_count' => 0, 'completed_count' => 0, 'completion_rate_bps' => 0, 'total_volume' => '0', 'level' => 0, 'badges' => []],
        );
    }

    public function release(Request $request, P2pOrder $order, ConfirmReleaseAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->execute($order, $request->user()), $order, 'Escrow released — trade complete.');
    }

    public function cancel(Request $request, P2pOrder $order, CancelOrderAction $action): RedirectResponse
    {
        return $this->run(fn () => $action->execute($order, $request->user()), $order, 'Order cancelled.');
    }

    public function dispute(Request $request, P2pOrder $order, OpenDisputeAction $action): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:64'],
            'detail' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->run(
            fn () => $action->execute($order, $request->user(), $data['reason'], $data['detail'] ?? null),
            $order,
            'Dispute opened — an operator will review it.',
        );
    }

    public function addEvidence(Request $request, P2pOrder $order, AddDisputeEvidenceAction $action): RedirectResponse
    {
        $this->assertParty($request, $order);
        $order->loadMissing('dispute');
        abort_unless($order->dispute, 404);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $role = $order->buyer_id === $request->user()->getKey() ? 'buyer' : 'seller';

        try {
            $action->execute($order->dispute, $role, (string) $request->user()->getKey(), $request->file('file'), $data['note'] ?? null);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Evidence added to the dispute.');
    }

    public function disputeEvidence(Request $request, P2pDisputeEvidence $evidence): StreamedResponse
    {
        $order = $evidence->dispute?->order;
        abort_unless($order, 404);
        $this->assertParty($request, $order);

        abort_unless(Storage::disk('local')->exists($evidence->path), 404);

        return Storage::disk('local')->download($evidence->path);
    }

    private function run(callable $fn, P2pOrder $order, string $success): RedirectResponse
    {
        try {
            $fn();
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('p2p.order', $order)->with('success', $success);
    }

    private function assertParty(Request $request, P2pOrder $order): void
    {
        abort_unless(in_array($request->user()->getKey(), [$order->buyer_id, $order->seller_id], true), 403);
    }
}
