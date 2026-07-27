<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Analytics\FlowAnalytics;
use App\Domain\Exchange\Contracts\RateProvider;
use App\Domain\Exchange\ExchangeService;
use App\Domain\Exchange\ExecuteSwapAction;
use App\Domain\Exchange\SwapPolicy;
use App\Domain\Ledger\LedgerService;
use App\Enums\ConversionContext;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Conversion;
use App\Models\FxQuote;
use App\Models\TradingPair;
use App\Support\BaseCurrency;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Exchange / swap — traditional server-rendered MVC. {@see index()} renders the
 * swap form (assets, balances, recent swaps) plus, when a priced quote is flashed
 * in the session, the rate/spread + a Confirm form. {@see quote()} prices a swap
 * for a short TTL and redirects back with the quote flashed; {@see confirm()}
 * executes a still-valid quote via {@see ExchangeService}. Money-critical — quotes
 * expire and are re-checked on confirm.
 */
class ExchangeController extends Controller
{
    public function index(Request $request, LedgerService $ledger): View
    {
        $user = $request->user();
        $restrict = (bool) feature('exchange_restrict_pairs', false);

        $all = $this->allAssets();
        $fromIds = $this->fromAssetIds($all, $restrict);

        $pairs = $restrict
            ? TradingPair::where('is_active', true)->get(['from_asset_id', 'to_asset_id'])
                ->map(fn ($p) => ['from' => $p->from_asset_id, 'to' => $p->to_asset_id])->values()->all()
            : [];

        $balances = [];
        foreach ($all as $asset) {
            $balance = $ledger->availableBalance($user, $asset->id);
            $balances[$asset->id] = [
                'available' => $balance->toDecimal(),
                'formatted' => $balance->format(),
            ];
        }

        // Balances are pooled per coin, so exchange is coin↔coin — no network.
        // Each coin is represented by its canonical (lowest-id) network asset.
        $coins = $all->groupBy('currency_id')->map(function ($group) {
            $rep = $group->sortBy('id')->first();

            return [
                'assetId' => $rep->id,
                'symbol' => $rep->symbol,
                'name' => $rep->name,
            ];
        })->values();

        return view('frontend.exchange', [
            'restrictPairs' => $restrict,
            'assets' => $all,
            'fromAssetIds' => $fromIds->values()->all(),
            'pairs' => $pairs,
            'balances' => $balances,
            'coins' => $coins,
            'recentCount' => Conversion::where('user_id', $user->id)->count(),
            'quote' => session('quote'),
        ]);
    }

    /** Dedicated swap history page — the full, paginated list of the user's swaps. */
    public function history(Request $request): View
    {
        $user = $request->user();
        $userId = $user->id;

        $filters = [
            'context' => (string) $request->query('context', 'all'),
            'asset' => (string) $request->query('asset', 'all'),
            'search' => trim((string) $request->query('search', '')),
        ];

        // ── Account-wide stats (independent of the active filters) ──
        $base = Conversion::where('user_id', $userId);
        $stats = [
            'total' => (clone $base)->count(),
            'month' => (clone $base)->where('created_at', '>=', now()->startOfMonth())->count(),
            'volume' => $this->swapVolumeInBaseCurrency($user),
            'volume30d' => $this->swapVolumeInBaseCurrency($user, now()->subDays(30)),
        ];

        // Assets this user has swapped (either side) — powers the asset filter.
        $quoteIds = (clone $base)->pluck('quote_id');
        $assetIds = FxQuote::whereIn('id', $quoteIds)->get(['from_asset_id', 'to_asset_id'])
            ->flatMap(fn (FxQuote $q) => [$q->from_asset_id, $q->to_asset_id])->unique();
        $symbols = Asset::whereIn('id', $assetIds)->orderBy('symbol')->pluck('symbol')->unique()->values();

        // ── Filtered, paginated feed ──
        $query = Conversion::with(['quote.fromAsset', 'quote.toAsset'])->where('user_id', $userId);

        if (in_array($filters['context'], ['swap', 'ramp', 'card_settle'], true)) {
            $query->where('context', $filters['context']);
        }

        // Asset / search both match either side of the pair (from OR to symbol).
        $symbolNeedle = $filters['asset'] !== 'all' ? $filters['asset'] : ($filters['search'] !== '' ? $filters['search'] : null);
        if ($symbolNeedle !== null) {
            $exact = $filters['asset'] !== 'all';
            $query->whereHas('quote', function ($q) use ($symbolNeedle, $exact) {
                $match = fn ($a) => $exact ? $a->where('symbol', $symbolNeedle) : $a->where('symbol', 'ilike', '%'.$symbolNeedle.'%');
                $q->whereHas('fromAsset', $match)->orWhereHas('toAsset', $match);
            });
        }

        $swaps = $query
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (Conversion $c) {
                $q = $c->quote;
                if (! $q || ! $q->fromAsset || ! $q->toAsset) {
                    return null;
                }

                $spread = $q->toAsset->money($c->spread_amount ?? '0');
                $fee = $q->toAsset->money($c->fee_amount ?? '0');

                return [
                    'id' => $c->id,
                    'fromSymbol' => $q->fromAsset->symbol,
                    'toSymbol' => $q->toAsset->symbol,
                    'fromName' => $q->fromAsset->name,
                    'toName' => $q->toAsset->name,
                    'fromAmount' => $q->fromAsset->money($q->from_amount)->format(),
                    'toAmount' => $q->toAsset->money($q->to_amount)->format(),
                    'rate' => rtrim(rtrim($q->rate, '0'), '.'),
                    'marketRate' => $q->market_rate ? rtrim(rtrim($q->market_rate, '0'), '.') : null,
                    'spread' => $c->spread_amount > 0 ? $spread->format() : null,
                    'spreadBps' => (int) $q->spread_bps,
                    'fee' => $c->fee_amount > 0 ? $fee->format() : null,
                    'feeBps' => (int) $q->fee_bps,
                    'context' => $c->context?->label(),
                    'status' => ucfirst((string) $c->status),
                    'at' => $c->created_at->toIso8601String(),
                    'completedAt' => $c->completed_at?->toIso8601String(),
                ];
            });

        return view('frontend.swaps', [
            'swaps' => $swaps,
            'stats' => $stats,
            'filters' => $filters,
            'symbols' => $symbols,
        ]);
    }

    /**
     * All-time (or since $since) swap volume, valued in the user's base currency
     * from the amount paid in. Mirrors {@see FlowAnalytics}.
     */
    private function swapVolumeInBaseCurrency($user, ?\DateTimeInterface $since = null): string
    {
        $base = BaseCurrency::assetFor($user);
        if (! $base) {
            return '—';
        }

        $rates = app(RateProvider::class);
        $total = BigDecimal::zero();

        $conversions = Conversion::with('quote.fromAsset')
            ->where('user_id', $user->id)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->get();

        foreach ($conversions as $c) {
            $from = $c->quote?->fromAsset;
            if (! $from) {
                continue;
            }
            $total = $total->plus(
                BigDecimal::ofUnscaledValue($c->quote->from_amount, $from->decimals)->multipliedBy($rates->rate($from, $base))
            );
        }

        return Money::ofBase(
            $total->withPointMovedRight($base->decimals)->toScale(0, RoundingMode::DOWN)->toBigInteger(),
            $base->decimals,
            $base->symbol,
        )->format(2);
    }

    public function quote(Request $request, ExchangeService $exchange, SwapPolicy $policy): RedirectResponse
    {
        $validated = $request->validate([
            'fromAssetId' => ['required', 'integer'],
            'toAssetId' => ['required', 'integer'],
            'fromAmount' => ['required', 'string'],
        ]);

        if ($validated['fromAssetId'] === $validated['toAssetId']) {
            throw ValidationException::withMessages(['toAssetId' => 'Choose two different assets.']);
        }

        $from = Asset::find($validated['fromAssetId']);
        $to = Asset::find($validated['toAssetId']);
        if (! $from || ! $to) {
            throw ValidationException::withMessages(['fromAssetId' => 'Invalid asset selection.']);
        }

        try {
            $amount = Money::ofDecimal($validated['fromAmount'], $from->decimals, $from->symbol);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['fromAmount' => 'Enter a valid amount.']);
        }

        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['fromAmount' => 'Amount must be greater than zero.']);
        }

        // Fail fast on eligibility/limits before pricing (authoritative re-check
        // happens again on confirm inside ExecuteSwapAction).
        try {
            $policy->assertEligible($request->user());
            $policy->assertWithinDailyLimit($request->user(), $from, $amount);
            $quote = $exchange->quote($request->user(), $from, $to, $amount, ConversionContext::Swap);
        } catch (RuntimeException $e) {
            // Expected business failure (eligibility, limit, unsupported pair, no rate…) — safe to show.
            throw ValidationException::withMessages(['fromAmount' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Swap quote failed unexpectedly', [
                'user_id' => $request->user()->id,
                'from' => $from->symbol,
                'to' => $to->symbol,
                'exception' => $e,
            ]);

            throw ValidationException::withMessages(['fromAmount' => __('Something went wrong. Please try again.')]);
        }

        return redirect()->route('exchange.index')
            ->with('quote', $this->quoteView($quote->load(['fromAsset', 'toAsset'])))
            ->withInput();
    }

    public function confirm(Request $request, ExecuteSwapAction $action): RedirectResponse
    {
        $validated = $request->validate(['quoteId' => ['required', 'string']]);

        $quote = FxQuote::with(['fromAsset', 'toAsset'])->find($validated['quoteId']);
        if (! $quote || $quote->user_id !== $request->user()->id) {
            // No valid quote to re-open the panel with — surface as a top-level
            // toast (session error) and keep the error bag for the inline field.
            return redirect()->route('exchange.index')
                ->with('error', __('Quote not found. Please request a new one.'))
                ->withErrors(['quoteId' => __('Quote not found. Please request a new one.')]);
        }

        if ($quote->expires_at->isPast()) {
            return redirect()->route('exchange.index')
                ->with('error', __('Quote expired. Please request a new quote.'))
                ->withErrors(['quoteId' => __('Quote expired. Please request a new quote.')]);
        }

        // Idempotent by the quote itself — a double-submit returns the same
        // conversion instead of swapping twice (the Action derives the key).
        try {
            $action->execute($request->user(), $quote);
        } catch (RuntimeException $e) {
            // Expected business failure (liquidity, daily limit, KYC, disabled
            // pair…) — show the exact reason and keep the confirm panel open.
            return $this->backToConfirm($quote, $e->getMessage());
        } catch (\Throwable $e) {
            // Unexpected — log with context, never leak internals to the user.
            Log::error('Swap confirm failed unexpectedly', [
                'user_id' => $request->user()->id,
                'quote_id' => $quote->id,
                'exception' => $e,
            ]);

            return $this->backToConfirm($quote, __('Something went wrong. Please try again.'));
        }

        return redirect()->route('exchange.index')->with('success', 'Swap complete.');
    }

    /**
     * Redirect back to the swap form with the confirm panel still open and the
     * failure surfaced both inline (quoteId error) and as a toast. Re-flashing
     * the quote + the original form input is what keeps {@see index()}'s
     * `$activeQuote` alive across the redirect — without it the panel (and its
     * error) would vanish, which read as a silent failure.
     */
    private function backToConfirm(FxQuote $quote, string $message): RedirectResponse
    {
        $quote->loadMissing(['fromAsset', 'toAsset']);
        $view = $this->quoteView($quote);

        return redirect()->route('exchange.index')
            ->with('quote', $view)
            ->with('error', $message)
            ->withErrors(['quoteId' => $message])
            ->withInput([
                'fromAssetId' => $view['fromAssetId'],
                'toAssetId' => $view['toAssetId'],
                'fromAmount' => $view['fromAmountInput'],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteView(FxQuote $quote): array
    {
        $from = $quote->fromAsset;
        $to = $quote->toAsset;

        return [
            'quoteId' => $quote->id,
            'fromAssetId' => $from->id,
            'toAssetId' => $to->id,
            'fromAmountInput' => $from->money($quote->from_amount)->toDecimal(),
            'toAmount' => Money::ofBase($quote->to_amount, $to->decimals, $to->symbol)->format(),
            'fromAmount' => Money::ofBase($quote->from_amount, $from->decimals, $from->symbol)->format(),
            'rate' => rtrim(rtrim(number_format((float) $quote->rate, 8, '.', ''), '0'), '.'),
            'marketRate' => $quote->market_rate
                ? rtrim(rtrim(number_format((float) $quote->market_rate, 8, '.', ''), '0'), '.')
                : null,
            'fromSymbol' => $from->symbol,
            'toSymbol' => $to->symbol,
            'spread' => number_format($quote->spread_bps / 100, 2),
            'fee' => number_format($quote->fee_bps / 100, 2),
            // Notional value of the swap in the user's base currency (display only).
            'valueBase' => $this->amountInBaseCurrency($quote->user, $from, $quote->from_amount),
            'expiresAt' => $quote->expires_at->timestamp,
            'expired' => $quote->expires_at->isPast(),
        ];
    }

    /** Value `$baseUnits` of `$asset` in the user's base currency, or null. */
    private function amountInBaseCurrency($user, Asset $asset, string $baseUnits): ?string
    {
        $base = BaseCurrency::assetFor($user);
        if (! $base) {
            return null;
        }

        $rate = app(RateProvider::class)->rate($asset, $base);
        $value = BigDecimal::ofUnscaledValue($baseUnits, $asset->decimals)->multipliedBy($rate);

        return Money::ofBase(
            $value->withPointMovedRight($base->decimals)->toScale(0, RoundingMode::DOWN)->toBigInteger(),
            $base->decimals,
            $base->symbol,
        )->format(2);
    }

    /** @return Collection<int, Asset> */
    private function allAssets(): Collection
    {
        return Asset::with('chain')->where('is_active', true)->orderBy('sort')->orderBy('symbol')->get();
    }

    /** @return Collection<int, int> Selectable FROM asset ids. */
    private function fromAssetIds(Collection $all, bool $restrict): Collection
    {
        if (! $restrict) {
            return $all->pluck('id');
        }

        $allowed = TradingPair::where('is_active', true)->pluck('from_asset_id')->unique();

        return $all->pluck('id')->intersect($allowed)->values();
    }
}
