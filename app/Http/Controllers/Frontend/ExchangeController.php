<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Exchange\ExchangeService;
use App\Domain\Ledger\LedgerService;
use App\Enums\ConversionContext;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Conversion;
use App\Models\FxQuote;
use App\Models\TradingPair;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
        $swaps = Conversion::with(['quote.fromAsset', 'quote.toAsset'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20)
            ->through(function (Conversion $c) {
                $q = $c->quote;
                if (! $q || ! $q->fromAsset || ! $q->toAsset) {
                    return null;
                }

                return [
                    'fromSymbol' => $q->fromAsset->symbol,
                    'toSymbol' => $q->toAsset->symbol,
                    'fromAmount' => $q->fromAsset->money($q->from_amount)->format(),
                    'toAmount' => $q->toAsset->money($q->to_amount)->format(),
                    'rate' => $q->toAsset->symbol,
                    'at' => $c->created_at->toIso8601String(),
                ];
            });

        return view('frontend.swaps', ['swaps' => $swaps]);
    }

    public function quote(Request $request, ExchangeService $exchange): RedirectResponse
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

        try {
            $quote = $exchange->quote($request->user(), $from, $to, $amount, ConversionContext::Swap);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['fromAmount' => $e->getMessage()]);
        }

        return redirect()->route('exchange.index')
            ->with('quote', $this->quoteView($quote->load(['fromAsset', 'toAsset'])))
            ->withInput();
    }

    public function confirm(Request $request, ExchangeService $exchange): RedirectResponse
    {
        $validated = $request->validate(['quoteId' => ['required', 'string']]);

        $quote = FxQuote::with(['fromAsset', 'toAsset'])->find($validated['quoteId']);
        if (! $quote || $quote->user_id !== $request->user()->id) {
            throw ValidationException::withMessages(['quoteId' => 'Quote not found. Please request a new one.']);
        }

        if ($quote->expires_at->isPast()) {
            throw ValidationException::withMessages(['quoteId' => 'Quote expired. Please request a new quote.']);
        }

        try {
            $exchange->execute($request->user(), $quote, Str::uuid()->toString());
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['quoteId' => $e->getMessage()]);
        }

        return redirect()->route('exchange.index')->with('success', 'Swap complete.');
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
            'fromSymbol' => $from->symbol,
            'toSymbol' => $to->symbol,
            'spread' => number_format($quote->spread_bps / 100, 2),
            'expiresAt' => $quote->expires_at->timestamp,
            'expired' => $quote->expires_at->isPast(),
        ];
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
