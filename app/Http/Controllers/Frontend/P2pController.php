<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Audit\ActivityLogger;
use App\Domain\P2p\AddDisputeEvidenceAction;
use App\Domain\P2p\CancelOrderAction;
use App\Domain\P2p\ConfirmReleaseAction;
use App\Domain\P2p\CreateAdAction;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\DuplicateAdAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Domain\P2p\OpenDisputeAction;
use App\Domain\P2p\P2pReputationService;
use App\Domain\P2p\SubmitReviewAction;
use App\Domain\P2p\UpdateAdAction;
use App\Enums\P2pAdStatus;
use App\Enums\P2pAdType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureP2pEnabled;
use App\Models\Asset;
use App\Models\P2pAd;
use App\Models\P2pBlock;
use App\Models\P2pDisputeEvidence;
use App\Models\P2pFavorite;
use App\Models\P2pMerchantProfile;
use App\Models\P2pOrder;
use App\Models\P2pPaymentMethod;
use App\Models\P2pReview;
use App\Models\P2pUserPaymentMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    /** Sort keys the marketplace accepts (see {@see applyMarketplaceSort()}). */
    private const MARKETPLACE_SORTS = ['recommended', 'price', 'completion', 'fast_release', 'trades'];

    /** Marketplace: browse ads on the opposite side of what you want to do. */
    public function index(Request $request): View
    {
        $want = $request->query('side', 'buy') === 'sell' ? 'sell' : 'buy';
        $adSide = $want === 'buy' ? P2pAdType::Sell : P2pAdType::Buy;

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'method' => (string) $request->query('method', ''),
            'amount' => is_numeric($request->query('amount')) ? (string) $request->query('amount') : '',
            'verified' => $request->boolean('verified'),
            'online' => $request->boolean('online'),
            'fav' => $request->boolean('fav'),
            'express' => $request->boolean('express'),
            'sort' => in_array($request->query('sort'), self::MARKETPLACE_SORTS, true) ? (string) $request->query('sort') : 'recommended',
        ];

        $ads = $this->marketplaceQuery($adSide, (string) $request->user()->getKey(), $filters)
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
            'filters' => $filters,
        ]);
    }

    /**
     * The marketplace ad book with server-side search, filters and sort. Joins
     * the advertiser's reputation profile so stat-based sorts/filters run in the
     * database (client-side filtering only ever saw the current page).
     *
     * @param  array{q:string, method:string, amount:string, verified:bool, online:bool, fav:bool, express:bool, sort:string}  $f
     * @return Builder<P2pAd>
     */
    private function marketplaceQuery(P2pAdType $adSide, string $uid, array $f)
    {
        $query = P2pAd::query()
            ->with(['user', 'asset', 'paymentMethods'])
            ->select('p2p_ads.*')
            ->leftJoin('p2p_merchant_profiles as pr', 'pr.user_id', '=', 'p2p_ads.user_id')
            ->where('p2p_ads.side', $adSide->value)
            ->where('p2p_ads.status', P2pAdStatus::Active->value)
            ->where('p2p_ads.user_id', '!=', $uid)
            ->where('p2p_ads.available_amount', '>', '0')
            ->where(fn ($w) => $w->whereNull('pr.vacation_mode')->orWhere('pr.vacation_mode', false))
            // Hide ads from anyone blocked in either direction.
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('p2p_blocks')->where(fn ($w) => $w
                ->where(fn ($x) => $x->where('user_id', $uid)->whereColumn('blocked_id', 'p2p_ads.user_id'))
                ->orWhere(fn ($x) => $x->where('blocked_id', $uid)->whereColumn('user_id', 'p2p_ads.user_id'))));

        if ($f['fav']) {
            $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('p2p_favorites')
                ->where('user_id', $uid)->whereColumn('merchant_id', 'p2p_ads.user_id'));
        }

        if ($f['q'] !== '') {
            $query->whereHas('user', fn ($u) => $u->where('name', 'ilike', '%'.$f['q'].'%'));
        }
        if ($f['method'] !== '') {
            $query->whereHas('paymentMethods', fn ($m) => $m->where('p2p_payment_methods.id', $f['method']));
        }
        if ($f['amount'] !== '' && (float) $f['amount'] > 0) {
            $query->where('p2p_ads.min_order', '<=', $f['amount'])->where('p2p_ads.max_order', '>=', $f['amount']);
        }
        if ($f['verified']) {
            $query->where('pr.level', '>=', 2);
        }
        if ($f['online']) {
            $query->where('pr.is_online', true);
        }
        if ($f['express'] ?? false) {
            $query->where('p2p_ads.is_express', true);
        }

        return $this->applyMarketplaceSort($query, $f['sort'], $adSide);
    }

    /**
     * @param  Builder<P2pAd>  $query
     * @return Builder<P2pAd>
     */
    private function applyMarketplaceSort($query, string $sort, P2pAdType $adSide)
    {
        $sorted = match ($sort) {
            'price' => $query->orderByRaw('p2p_ads.fixed_price '.($adSide === P2pAdType::Sell ? 'asc' : 'desc').' nulls last'),
            'completion' => $query->orderByRaw('pr.completion_rate_bps desc nulls last'),
            'fast_release' => $query->orderByRaw('pr.avg_release_seconds asc nulls last'),
            'trades' => $query->orderByRaw('pr.trade_count desc nulls last'),
            default => $query->orderByRaw('case when pr.featured_until > now() then 1 else 0 end desc')
                ->orderByDesc('p2p_ads.is_express')
                ->orderByDesc('p2p_ads.priority')
                ->orderByRaw('pr.is_online desc nulls last')
                ->orderByRaw('pr.completion_rate_bps desc nulls last'),
        };

        // Deterministic tiebreaker so pagination is stable.
        return $sorted->orderBy('p2p_ads.id');
    }

    /** Ad status buckets that power the quick-filter tabs. */
    private const AD_TABS = [
        'active' => ['active'],
        'paused' => ['paused'],
        'closed' => ['disabled', 'archived', 'draft'],
    ];

    /** Merchant P2P hub: reputation, ad/order KPIs, and trend charts. */
    public function dashboard(Request $request): View
    {
        $uid = (string) $request->user()->getKey();
        $profile = $this->profileFor($uid);
        $rep = app(P2pReputationService::class);

        $mine = fn () => P2pOrder::query()->where(fn ($q) => $q->where('buyer_id', $uid)->orWhere('seller_id', $uid));

        // Ad counts by status.
        $adCounts = P2pAd::where('user_id', $uid)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $activeAds = (int) ($adCounts['active'] ?? 0);
        $totalAds = (int) $adCounts->sum();

        // Order counts by status (all-time) → outcome doughnut + open count.
        $byStatus = $mine()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $sum = fn (...$s) => collect($s)->sum(fn ($k) => (int) ($byStatus[$k] ?? 0));
        $completed = $sum('completed', 'force_released');
        $cancelled = $sum('cancelled', 'expired', 'force_cancelled');
        $open = $sum('waiting_payment', 'buyer_paid', 'releasing');
        $disputed = $sum('disputed');

        // 30-day window: order count, success rate, traded volume (successful).
        $since = now()->subDays(30);
        $orders30 = (int) $mine()->where('created_at', '>=', $since)->count();
        $success30 = (int) $mine()->where('created_at', '>=', $since)->whereIn('status', ['completed', 'force_released'])->count();
        $vol30 = Money::ofBase((string) ($mine()->where('created_at', '>=', $since)
            ->whereIn('status', ['completed', 'force_released'])->sum('crypto_amount') ?: '0'), 6, 'USDT');

        // 14-day daily traded-volume series (USDT).
        $days = 14;
        $start = now()->subDays($days - 1)->startOfDay();
        $series = $mine()->whereIn('status', ['completed', 'force_released'])
            ->where('created_at', '>=', $start)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as d, sum(crypto_amount) as v")
            ->groupBy('d')->pluck('v', 'd');
        $labels = [];
        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $labels[] = $day->format('M j');
            $data[] = round(((float) ($series[$day->toDateString()] ?? 0)) / 1_000_000, 2);
        }

        $kpis = [
            ['label' => __('Active ads'), 'value' => number_format($activeAds), 'icon' => 'megaphone', 'accent' => 'brand'],
            ['label' => __('Open orders'), 'value' => number_format($open), 'icon' => 'clock', 'accent' => 'sky'],
            ['label' => __('Completed trades'), 'value' => number_format((int) $profile->completed_count), 'icon' => 'check-badge', 'accent' => 'emerald'],
            ['label' => __('Completion rate'), 'value' => number_format($profile->completion_rate_bps / 100, 1).'%', 'icon' => 'chart-bar', 'accent' => 'violet'],
            ['label' => __('30-day volume'), 'value' => $vol30->format(), 'icon' => 'banknotes', 'accent' => 'emerald'],
            ['label' => __('Avg. release'), 'value' => $profile->avg_release_seconds ? max(1, (int) round($profile->avg_release_seconds / 60)).'m' : '—', 'icon' => 'bolt', 'accent' => 'amber'],
            ['label' => __('Positive feedback'), 'value' => $profile->review_count > 0 ? number_format($profile->positivePercent(), 1).'%' : '—', 'icon' => 'hand-thumb-up', 'accent' => 'emerald'],
            ['label' => __('Lifetime volume'), 'value' => Money::ofBase((string) ($profile->total_volume ?: '0'), 6, 'USDT')->format(), 'icon' => 'trophy', 'accent' => 'brand'],
        ];

        return view('frontend.p2p.dashboard', [
            'profile' => $profile,
            'rep' => $rep,
            'kpis' => $kpis,
            'activeAds' => $activeAds,
            'totalAds' => $totalAds,
            'orders30' => $orders30,
            'successRate30' => $orders30 > 0 ? round($success30 / $orders30 * 100, 1) : 0.0,
            'volumeChart' => [
                'id' => 'p2p-volume', 'title' => __('Traded volume (14 days)'), 'subtitle' => 'USDT',
                'type' => 'area', 'span' => 'full', 'labels' => $labels,
                'datasets' => [['label' => __('Volume'), 'data' => $data, 'color' => '#2563eb']],
            ],
            'outcomeChart' => [
                'id' => 'p2p-outcomes', 'title' => __('Order outcomes'), 'type' => 'doughnut',
                'labels' => [__('Completed'), __('Cancelled/Expired'), __('Open'), __('Disputed')],
                'datasets' => [['data' => [$completed, $cancelled, $open, $disputed]]],
            ],
        ]);
    }

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
            'total_amount' => ['required', 'numeric', 'min:100'],
            'payment_window_min' => ['required', 'integer', 'min:5', 'max:180'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'auto_reply' => ['nullable', 'string', 'max:1000'],
            'is_express' => ['nullable', 'boolean'],
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
                'auto_reply' => $data['auto_reply'] ?? null,
                'is_express' => $request->boolean('is_express'),
                'payment_method_ids' => $data['payment_method_ids'],
            ]);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('p2p.ads')->with('success', 'Your ad is live.');
    }

    /** Apply one action to a batch of the caller's own ads. */
    public function bulkAds(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:pause,resume,archive,delete'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        $ads = P2pAd::where('user_id', $request->user()->getKey())->whereIn('id', $data['ids'])->get();
        $open = ['waiting_payment', 'buyer_paid', 'releasing', 'disputed'];
        $done = 0;
        $skipped = 0;

        foreach ($ads as $ad) {
            $target = match ($data['action']) {
                'pause' => $ad->status === P2pAdStatus::Active ? P2pAdStatus::Paused : null,
                'resume' => $ad->status === P2pAdStatus::Paused ? P2pAdStatus::Active : null,
                'archive' => $ad->status !== P2pAdStatus::Archived ? P2pAdStatus::Archived : null,
                default => null,
            };

            if ($data['action'] === 'delete') {
                if ($ad->orders()->whereIn('status', $open)->exists()) {
                    $skipped++;
                } else {
                    $ad->delete();
                    $done++;
                }
            } elseif ($target !== null) {
                $ad->update(['status' => $target]);
                $done++;
            }
        }

        ActivityLogger::log('p2p.ads.bulk', null, ['action' => $data['action'], 'done' => $done, 'skipped' => $skipped], actor: $request->user());

        $msg = trans_choice('{1}:count ad updated|[2,*]:count ads updated', $done, ['count' => $done]);
        if ($skipped > 0) {
            $msg .= ' · '.$skipped.' '.__('skipped (open orders)');
        }

        return back()->with($done > 0 ? 'success' : 'error', $done > 0 ? $msg : __('No ads were updated.'));
    }

    public function toggleAd(Request $request, P2pAd $ad): RedirectResponse
    {
        abort_unless($ad->user_id === $request->user()->getKey(), 403);

        $next = $ad->status === P2pAdStatus::Active ? P2pAdStatus::Paused : P2pAdStatus::Active;
        $ad->update(['status' => $next]);
        ActivityLogger::log('p2p.ad.toggled', $ad, ['status' => $next->value], actor: $request->user());

        return back()->with('success', 'Ad '.($next === P2pAdStatus::Active ? 'resumed' : 'paused').'.');
    }

    public function duplicateAd(Request $request, P2pAd $ad, DuplicateAdAction $action): RedirectResponse
    {
        abort_unless($ad->user_id === $request->user()->getKey(), 403);

        try {
            $copy = $action->execute($request->user(), $ad);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('p2p.ads.edit', $copy)->with('success', 'Ad duplicated as a draft — review and publish it.');
    }

    public function destroyAd(Request $request, P2pAd $ad): RedirectResponse
    {
        abort_unless($ad->user_id === $request->user()->getKey(), 403);

        if ($ad->orders()->whereIn('status', ['waiting_payment', 'buyer_paid', 'releasing', 'disputed'])->exists()) {
            return back()->with('error', 'This ad has open orders — resolve them before deleting it.');
        }

        $ad->delete();
        ActivityLogger::log('p2p.ad.deleted', $ad, [], actor: $request->user());

        return redirect()->route('p2p.ads')->with('success', 'Ad deleted.');
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
            'total_amount' => ['required', 'numeric', 'min:100'],
            'payment_window_min' => ['required', 'integer', 'min:5', 'max:180'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'auto_reply' => ['nullable', 'string', 'max:1000'],
            'is_express' => ['nullable', 'boolean'],
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
                'auto_reply' => $data['auto_reply'] ?? null,
                'is_express' => $request->boolean('is_express'),
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
        $order->load(['ad.paymentMethods', 'buyer', 'seller', 'asset', 'escrow', 'paymentMethod', 'dispute.evidence', 'reviews']);

        // The seller's payout accounts for this order's rail — surfaced to the buyer
        // (who pays the fiat) once an order is open. When the order didn't pin a
        // specific method, fall back to every rail the ad accepts so the seller's
        // saved accounts still show up.
        $methodIds = $order->payment_method_id
            ? [$order->payment_method_id]
            : $order->ad->paymentMethods->modelKeys();

        $payToAccounts = ! empty($methodIds)
            ? P2pUserPaymentMethod::with('method')
                ->where('user_id', $order->seller_id)
                ->whereIn('payment_method_id', $methodIds)
                ->where('is_active', true)
                ->orderByDesc('is_default')->get()
            : collect();

        return view('frontend.p2p.order', [
            'order' => $order,
            'me' => $request->user()->getKey(),
            'isBuyer' => $order->buyer_id === $request->user()->getKey(),
            'payToAccounts' => $payToAccounts,
            'myReview' => $order->reviewBy((string) $request->user()->getKey()),
        ]);
    }

    public function paymentMethods(Request $request): View
    {
        $methods = P2pPaymentMethod::where('is_active', true)->orderBy('sort')->get();

        return view('frontend.p2p.payment-methods', [
            'accounts' => P2pUserPaymentMethod::with('method')
                ->where('user_id', $request->user()->getKey())
                ->orderByDesc('is_default')->latest()->get(),
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

        // The first account a user saves becomes their default payout rail.
        $isFirst = ! P2pUserPaymentMethod::where('user_id', $request->user()->getKey())->exists();

        P2pUserPaymentMethod::create([
            'user_id' => $request->user()->getKey(),
            'payment_method_id' => $method->id,
            'label' => $data['label'] ?? null,
            'account' => $account,
            'is_active' => true,
            'is_default' => $isFirst,
        ]);

        return back()->with('success', 'Payment account added.');
    }

    public function setDefaultPaymentMethod(Request $request, P2pUserPaymentMethod $method): RedirectResponse
    {
        abort_unless($method->user_id === $request->user()->getKey(), 403);

        DB::transaction(function () use ($request, $method) {
            P2pUserPaymentMethod::where('user_id', $request->user()->getKey())->update(['is_default' => false]);
            $method->update(['is_default' => true, 'is_active' => true]);
        });

        return back()->with('success', 'Default payout account updated.');
    }

    /**
     * The field schema for a method, falling back to a generic name+number pair
     * if an admin hasn't configured one yet.
     *
     * @return array<int, array{key: string, label: string, required: bool}>
     */
    private function methodFields(P2pPaymentMethod $method): array
    {
        return $method->fieldSchema();
    }

    public function destroyPaymentMethod(Request $request, P2pUserPaymentMethod $method): RedirectResponse
    {
        abort_unless($method->user_id === $request->user()->getKey(), 403);

        $wasDefault = $method->is_default;
        $method->delete();

        // Promote another account so the user always has a default if any remain.
        if ($wasDefault) {
            $next = P2pUserPaymentMethod::where('user_id', $request->user()->getKey())->oldest()->first();
            $next?->update(['is_default' => true]);
        }

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

        $reviews = P2pReview::with('rater')
            ->where('ratee_id', $user->getKey())
            ->latest()
            ->limit(20)
            ->get();

        $me = (string) $request->user()->getKey();

        return view('frontend.p2p.merchant', [
            'trader' => $user,
            'profile' => $profile,
            'ads' => $ads,
            'isSelf' => $user->getKey() === $request->user()->getKey(),
            'reviews' => $reviews,
            'isFavourite' => P2pFavorite::where('user_id', $me)->where('merchant_id', $user->getKey())->exists(),
            'isBlocked' => P2pBlock::where('user_id', $me)->where('blocked_id', $user->getKey())->exists(),
        ]);
    }

    public function toggleFavourite(Request $request, User $user): RedirectResponse
    {
        $me = (string) $request->user()->getKey();
        abort_if($user->getKey() === $me, 403);

        $fav = P2pFavorite::where('user_id', $me)->where('merchant_id', $user->getKey())->first();
        if ($fav) {
            $fav->delete();

            return back()->with('success', 'Removed from favourites.');
        }

        P2pFavorite::create(['user_id' => $me, 'merchant_id' => $user->getKey()]);

        return back()->with('success', 'Added to favourites.');
    }

    public function toggleBlock(Request $request, User $user): RedirectResponse
    {
        $me = (string) $request->user()->getKey();
        abort_if($user->getKey() === $me, 403);

        $block = P2pBlock::where('user_id', $me)->where('blocked_id', $user->getKey())->first();
        if ($block) {
            $block->delete();

            return back()->with('success', 'Merchant unblocked.');
        }

        P2pBlock::create(['user_id' => $me, 'blocked_id' => $user->getKey()]);

        return back()->with('success', 'Merchant blocked — you will no longer trade or see their ads.');
    }

    public function toggleOnline(Request $request): RedirectResponse
    {
        $profile = $this->profileFor($request->user()->getKey());
        $goingOnline = ! $profile->is_online;
        $profile->update([
            'is_online' => $goingOnline,
            // Stamp activity so the presence sweep doesn't immediately flip them back.
            'last_seen_at' => $goingOnline ? now() : $profile->last_seen_at,
        ]);

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

    public function review(Request $request, P2pOrder $order, SubmitReviewAction $action): RedirectResponse
    {
        $this->assertParty($request, $order);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->run(
            fn () => $action->execute($order, $request->user(), (int) $data['rating'], $data['comment'] ?? null),
            $order,
            'Thanks — your feedback has been recorded.',
        );
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
