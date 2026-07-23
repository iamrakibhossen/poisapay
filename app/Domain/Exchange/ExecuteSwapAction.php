<?php

declare(strict_types=1);

namespace App\Domain\Exchange;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\LedgerService;
use App\Enums\ConversionContext;
use App\Events\SwapExecuted;
use App\Models\Conversion;
use App\Models\FxQuote;
use App\Models\User;
use Throwable;

/**
 * Orchestrates a user-initiated swap end to end: applies the swap-only policy
 * (feature flag, KYC, daily limit), executes the shared exchange engine
 * idempotently (one quote = one swap), stamps the USD notional, and writes a
 * full audit trail (before/after balances, market/locked rate, rate source,
 * spread/fee — IP + user agent captured by {@see ActivityLogger}). Ramp and
 * card-settlement conversions bypass this and call {@see ExchangeService::execute}
 * directly, so their behaviour is untouched.
 */
class ExecuteSwapAction
{
    public function __construct(
        private readonly ExchangeService $exchange,
        private readonly SwapPolicy $policy,
        private readonly LedgerService $ledger,
    ) {}

    public function execute(User $user, FxQuote $quote, ?string $idempotencyKey = null): Conversion
    {
        $from = $quote->fromAsset;
        $to = $quote->toAsset;
        $fromAmount = $from->money($quote->from_amount);

        // Swap-context policy (ramp/card settlement price without these gates).
        if ($quote->context === ConversionContext::Swap) {
            $this->policy->assertEligible($user);
            $this->policy->assertWithinDailyLimit($user, $from, $fromAmount);
        }

        // One quote = one swap: a stable, quote-derived key makes a double-submit
        // return the same conversion instead of swapping twice.
        $key = $idempotencyKey ?: 'swap:'.$quote->id;

        $beforeFrom = $this->ledger->availableBalance($user, $from->id);
        $beforeTo = $this->ledger->availableBalance($user, $to->id);

        try {
            $conversion = $this->exchange->execute($user, $quote, $key);
        } catch (Throwable $e) {
            ActivityLogger::log('swap.failed', $quote, [
                'from' => $from->symbol,
                'to' => $to->symbol,
                'from_amount' => $fromAmount->toDecimal(),
                'reason' => $e->getMessage(),
            ], 'Swap failed', $user);

            throw $e;
        }

        // Stamp USD notional once (safe under idempotent replay).
        if ($conversion->notional_usd === null) {
            $conversion->forceFill(['notional_usd' => $this->policy->notionalUsd($from, $fromAmount)])->save();
        }

        $afterFrom = $this->ledger->availableBalance($user, $from->id);
        $afterTo = $this->ledger->availableBalance($user, $to->id);

        ActivityLogger::log('swap.completed', $conversion, [
            'from' => $from->symbol,
            'to' => $to->symbol,
            'from_amount' => $fromAmount->toDecimal(),
            'to_amount' => $to->money($quote->to_amount)->toDecimal(),
            'market_rate' => $quote->market_rate,
            'locked_rate' => $quote->rate,
            'spread_bps' => $quote->spread_bps,
            'fee_bps' => $quote->fee_bps,
            'rate_source' => $quote->source,
            'notional_usd' => $conversion->notional_usd,
            'before' => [$from->symbol => $beforeFrom->toDecimal(), $to->symbol => $beforeTo->toDecimal()],
            'after' => [$from->symbol => $afterFrom->toDecimal(), $to->symbol => $afterTo->toDecimal()],
        ], 'Swap completed', $user);

        SwapExecuted::dispatch($conversion->id);

        return $conversion;
    }
}
