<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Exchange\ExchangeService;
use App\Domain\Exchange\ExecuteSwapAction;
use App\Domain\Exchange\SwapPolicy;
use App\Enums\ConversionContext;
use App\Models\Asset;
use App\Models\Conversion;
use App\Models\FxQuote;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Swap API (§F2/§8): price a short-lived quote, then execute it. Execution is
 * idempotent — pass an `Idempotency-Key` header (falls back to the quote id) so
 * a retry never swaps twice. Reuses the same {@see ExecuteSwapAction} and policy
 * as the web flow; no accounting logic lives here.
 */
class SwapController extends ApiController
{
    public function quote(Request $request, ExchangeService $exchange, SwapPolicy $policy): JsonResponse
    {
        $data = $request->validate([
            'from' => 'required|string',
            'to' => 'required|string',
            'amount' => 'required|numeric|gt:0',
        ]);

        if (strcasecmp($data['from'], $data['to']) === 0) {
            return $this->fail('invalid_pair', 'Choose two different assets.', [], 422);
        }

        $from = Asset::where('symbol', $data['from'])->where('is_active', true)->first();
        $to = Asset::where('symbol', $data['to'])->where('is_active', true)->first();
        if (! $from || ! $to) {
            return $this->fail('asset_not_found', 'Unknown asset.', [], 404);
        }

        try {
            $amount = Money::ofDecimal($data['amount'], $from->decimals, $from->symbol);
            $policy->assertEligible($request->user());
            $policy->assertWithinDailyLimit($request->user(), $from, $amount);
            $quote = $exchange->quote($request->user(), $from, $to, $amount, ConversionContext::Swap);
        } catch (Throwable $e) {
            return $this->fail('quote_failed', $e->getMessage(), [], 422);
        }

        return $this->ok($this->quotePayload($quote->load(['fromAsset', 'toAsset'])), 201);
    }

    public function store(Request $request, ExecuteSwapAction $action): JsonResponse
    {
        $data = $request->validate(['quote_id' => 'required|string']);

        $quote = FxQuote::with(['fromAsset', 'toAsset'])->find($data['quote_id']);
        if (! $quote || $quote->user_id !== $request->user()->id) {
            return $this->fail('quote_not_found', 'Quote not found. Request a new one.', [], 404);
        }
        if ($quote->expires_at->isPast()) {
            return $this->fail('quote_expired', 'Quote expired. Request a new one.', [], 422);
        }

        try {
            $conversion = $action->execute($request->user(), $quote, $this->idempotencyKey());
        } catch (Throwable $e) {
            return $this->fail('swap_failed', $e->getMessage(), [], 422);
        }

        return $this->ok($this->conversionPayload($conversion->fresh(), $quote), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $swaps = Conversion::with(['quote.fromAsset', 'quote.toAsset'])
            ->where('user_id', $request->user()->id)
            ->latest()->limit(50)->get()
            ->map(function (Conversion $c) {
                $q = $c->quote;

                return [
                    'id' => $c->id,
                    'status' => $c->status,
                    'from' => $q?->fromAsset?->symbol,
                    'to' => $q?->toAsset?->symbol,
                    'from_amount' => $q && $q->fromAsset ? $q->fromAsset->money($q->from_amount)->toDecimal() : null,
                    'to_amount' => $q && $q->toAsset ? $q->toAsset->money($q->to_amount)->toDecimal() : null,
                    'created_at' => $c->created_at->toIso8601String(),
                ];
            });

        return $this->ok($swaps);
    }

    /** @return array<string, mixed> */
    private function quotePayload(FxQuote $quote): array
    {
        $from = $quote->fromAsset;
        $to = $quote->toAsset;

        return [
            'quote_id' => $quote->id,
            'from' => $from->symbol,
            'to' => $to->symbol,
            'from_amount' => $from->money($quote->from_amount)->toDecimal(),
            'to_amount' => $to->money($quote->to_amount)->toDecimal(),
            'market_rate' => $quote->market_rate,
            'locked_rate' => $quote->rate,
            'spread_bps' => $quote->spread_bps,
            'fee_bps' => $quote->fee_bps,
            'expires_at' => $quote->expires_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function conversionPayload(Conversion $c, FxQuote $quote): array
    {
        $from = $quote->fromAsset;
        $to = $quote->toAsset;

        return [
            'id' => $c->id,
            'status' => $c->status,
            'from' => $from->symbol,
            'to' => $to->symbol,
            'from_amount' => $from->money($quote->from_amount)->toDecimal(),
            'to_amount' => $to->money($quote->to_amount)->toDecimal(),
            'gross_amount' => $to->money($c->gross_amount)->toDecimal(),
            'spread' => $from->money($c->spread_amount)->toDecimal(),
            'fee' => $from->money($c->fee_amount)->toDecimal(),
            'notional_usd' => $c->notional_usd,
            'completed_at' => optional($c->completed_at)->toIso8601String(),
        ];
    }
}
