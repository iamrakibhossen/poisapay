<?php

declare(strict_types=1);

namespace App\Domain\Exchange;

use App\Domain\Compliance\AccountGuard;
use App\Domain\Exchange\Contracts\RateProvider;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\ConversionContext;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\Conversion;
use App\Models\FxQuote;
use App\Models\TradingPair;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Quote-driven exchange engine (TDD §F2): crypto↔crypto, crypto↔fiat and the
 * JIT conversion cards depend on. Quotes are short-lived; execution re-validates
 * expiry, locks the from-amount, posts a balanced conversion and accrues spread
 * to fx:spread_income.
 */
class ExchangeService
{
    public function __construct(
        private readonly RateProvider $rates,
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    /** Produce a short-lived quote (seconds) with an explicit spread. */
    public function quote(User $user, Asset $from, Asset $to, Money $fromAmount, ConversionContext $context = ConversionContext::Swap): FxQuote
    {
        // Trading-pair policy (Phase 5): honour per-pair spread + optional restriction.
        $pair = TradingPair::for($from->id, $to->id);
        if ($pair && ! $pair->is_active) {
            throw new RuntimeException("Trading is disabled for {$from->symbol} → {$to->symbol}.");
        }
        if (! $pair && feature('exchange_restrict_pairs', false)) {
            throw new RuntimeException("{$from->symbol} → {$to->symbol} is not a supported trading pair.");
        }
        if ($pair) {
            $min = $from->money($pair->min_amount);
            if ($fromAmount->isLessThan($min) && $min->isPositive()) {
                throw new RuntimeException("Minimum for this pair is {$min->format()}.");
            }
            if ($pair->max_amount && $fromAmount->isGreaterThanOrEqual($from->money($pair->max_amount)) && ! $fromAmount->equals($from->money($pair->max_amount))) {
                throw new RuntimeException('Amount exceeds the maximum for this pair.');
            }
        }

        $mid = $this->rates->rate($from, $to);
        if ($mid->isZero()) {
            throw new RuntimeException("No rate available for {$from->symbol}->{$to->symbol}.");
        }

        $spreadBps = $pair?->spread_bps ?? (int) getSetting('exchange_spread_bps', config('poisapay.default_spread_bps', 75));
        // Optional explicit platform fee on top of the spread — user-initiated
        // swaps only (ramp/card settlement price at the spread alone).
        $feeBps = $context === ConversionContext::Swap
            ? (int) getSetting('exchange_fee_bps', config('poisapay.default_fee_bps', 0))
            : 0;

        // Charge spread + fee by giving the user slightly less of the target asset.
        $effective = $mid->multipliedBy(BigDecimal::of(10_000 - $spreadBps - $feeBps))->dividedBy(10_000, 18, RoundingMode::DOWN);

        // Convert base->base honouring both assets' decimals.
        $fromWhole = BigDecimal::ofUnscaledValue($fromAmount->baseString(), $from->decimals);
        $toWhole = $fromWhole->multipliedBy($effective);
        $toBase = $toWhole->withPointMovedRight($to->decimals)->toScale(0, RoundingMode::DOWN)->toBigInteger();

        return FxQuote::create([
            'user_id' => $user->id,
            'from_asset_id' => $from->id,
            'to_asset_id' => $to->id,
            'from_amount' => $fromAmount->baseString(),
            'to_amount' => (string) $toBase,
            'rate' => (string) $effective,
            'market_rate' => (string) $mid,
            'spread_bps' => $spreadBps,
            'fee_bps' => $feeBps,
            'source' => 'reference',
            'context' => $context,
            'expires_at' => now()->addSeconds(30),
        ]);
    }

    /** Execute a quote: lock from, credit to, accrue spread. Idempotent. */
    public function execute(User $user, FxQuote $quote, string $idempotencyKey): Conversion
    {
        AccountGuard::assertActive($user);
        if ($quote->expires_at->isPast()) {
            throw new RuntimeException('Quote has expired. Please request a new quote.');
        }
        if ($quote->user_id !== $user->id) {
            throw new RuntimeException('Quote does not belong to this user.');
        }

        return DB::transaction(function () use ($user, $quote, $idempotencyKey): Conversion {
            $existing = Conversion::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $from = $quote->fromAsset;
            $to = $quote->toAsset;

            $fromAvailable = $this->accounts->forUser($user, LedgerAccountType::UserAvailable, $from->id);
            $toAvailable = $this->accounts->forUser($user, LedgerAccountType::UserAvailable, $to->id);
            $treasuryFrom = $this->accounts->system(LedgerAccountType::TreasuryHot, $from->id);
            $treasuryTo = $this->accounts->system(LedgerAccountType::TreasuryHot, $to->id);

            $fromAmount = Money::ofBase($quote->from_amount, $from->decimals, $from->symbol);
            $toAmount = Money::ofBase($quote->to_amount, $to->decimals, $to->symbol);

            // Guard balance under lock.
            $row = DB::table('account_balances')->where('account_id', $fromAvailable->id)->lockForUpdate()->first();
            $current = Money::ofBase($row->balance ?? '0', $from->decimals, $from->symbol);
            if ($current->isLessThan($fromAmount)) {
                throw new RuntimeException('Insufficient balance for conversion.');
            }

            // The platform's profit is the spread (and optional platform fee),
            // booked explicitly in the from-asset to the revenue accounts so it
            // surfaces in the revenue wallet — the rest of the from-amount backs
            // the to-payout. treasury stays flat at mid; all margin is income.
            $fromBase = BigInteger::of($fromAmount->baseString());
            $spread = $fromBase->multipliedBy($quote->spread_bps)->dividedBy(10_000, RoundingMode::DOWN);
            $fee = $fromBase->multipliedBy($quote->fee_bps)->dividedBy(10_000, RoundingMode::DOWN);
            $treasuryPortion = $fromBase->minus($spread)->minus($fee);

            $lines = [
                // User surrenders the from-asset.
                PostingLine::debit($fromAvailable->id, $from->id, $fromAmount),
                PostingLine::credit($treasuryFrom->id, $from->id, (string) $treasuryPortion),
                // Deliver the to-asset from treasury to the user.
                PostingLine::debit($treasuryTo->id, $to->id, $toAmount),
                PostingLine::credit($toAvailable->id, $to->id, $toAmount),
            ];

            // Only book revenue lines when the amount is positive (keeps the
            // entry minimal and identical to before when fee_bps = 0).
            if ($spread->isPositive()) {
                $spreadIncome = $this->accounts->system(LedgerAccountType::FxSpreadIncome, $from->id);
                $lines[] = PostingLine::credit($spreadIncome->id, $from->id, (string) $spread);
            }
            if ($fee->isPositive()) {
                $feeIncome = $this->accounts->system(LedgerAccountType::FeeIncome, $from->id);
                $lines[] = PostingLine::credit($feeIncome->id, $from->id, (string) $fee);
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'exchange.convert',
                idempotencyKey: 'entry:'.$idempotencyKey,
                lines: $lines,
                memo: "Convert {$from->symbol} -> {$to->symbol}",
                metadata: ['quote_id' => $quote->id, 'spread' => (string) $spread, 'fee' => (string) $fee],
            ));

            // Gross (pre-spread/fee) to-amount at the market rate — a record-only
            // figure so the conversion is a self-contained swap receipt.
            $grossBase = BigDecimal::ofUnscaledValue($fromAmount->baseString(), $from->decimals)
                ->multipliedBy(BigDecimal::of($quote->market_rate ?: $quote->rate))
                ->withPointMovedRight($to->decimals)->toScale(0, RoundingMode::DOWN)->toBigInteger();

            return Conversion::create([
                'user_id' => $user->id,
                'quote_id' => $quote->id,
                'context' => $quote->context,
                'entry_id' => $entry->id,
                'idempotency_key' => $idempotencyKey,
                'status' => 'completed',
                'completed_at' => now(),
                'spread_amount' => (string) $spread,
                'fee_amount' => (string) $fee,
                'gross_amount' => (string) $grossBase,
            ]);
        });
    }
}
