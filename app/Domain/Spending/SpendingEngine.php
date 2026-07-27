<?php

declare(strict_types=1);

namespace App\Domain\Spending;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Compliance\AccountGuard;
use App\Domain\Exchange\ExchangeService;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Spending\DTO\SpendLeg;
use App\Domain\Spending\DTO\SpendRequest;
use App\Domain\Spending\DTO\SpendResult;
use App\Domain\Spending\Events\FundsSpent;
use App\Domain\Spending\Exceptions\InsufficientBalanceException;
use App\Enums\ConversionContext;
use App\Enums\LedgerAccountType;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

/**
 * The Global Spending Priority Engine — the SINGLE source of truth for spending a
 * user's balance. Every feature that spends (card, merchant, shop, payment link,
 * subscription, bill, invoice, …) calls {@see spend()} and never inspects balances
 * or selects assets itself.
 *
 * For one spend it: resolves priority, selects balances (partial consumption),
 * validates platform liquidity, auto-converts non-settlement coins into the
 * settlement asset through the audited exchange engine (spread → fx:spread_income,
 * a Conversion record per leg), then posts ONE balanced settlement entry debiting
 * the user's settlement balance into the caller's destination. Atomic (all legs +
 * settlement in one DB transaction), idempotent, audited and event-driven.
 */
class SpendingEngine
{
    public function __construct(
        private readonly SpendingPriorityResolver $resolver,
        private readonly SpendAssetSelector $selector,
        private readonly LiquidityValidator $liquidity,
        private readonly ExchangeService $exchange,
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function spend(SpendRequest $request): SpendResult
    {
        AccountGuard::assertActive($request->user);
        $request->assertValid();

        return DB::transaction(function () use ($request): SpendResult {
            // Idempotent replay: the settlement entry already exists.
            $existing = JournalEntry::where('idempotency_key', $request->idempotencyKey)->first();
            if ($existing) {
                return new SpendResult($existing, [], $request->amount, replayed: true);
            }

            $settlement = $request->settlementAsset;
            $required = $request->amount;

            // 1. Resolve priority, then 2. select balances (partial consumption).
            $candidates = $this->resolver->resolve($request->user, $settlement);
            $plan = $this->selector->plan($request->user, $settlement, $required, $candidates);

            if (! $plan->covers) {
                throw new InsufficientBalanceException('Insufficient balance.');
            }

            // 3. Validate platform liquidity before converting anything.
            $this->liquidity->assertCanConvert($settlement, $plan->conversionOutput);

            // 4. Auto-convert non-settlement legs into the user's settlement balance
            //    via the audited exchange engine (spread + Conversion record + gate).
            $legs = [];
            foreach ($plan->legs as $leg) {
                if (! $leg->converted) {
                    $legs[] = new SpendLeg($leg->asset, $leg->spend, $leg->settlementValue, false, null);

                    continue;
                }

                $quote = $this->exchange->quote($request->user, $leg->asset, $settlement, $leg->spend, ConversionContext::Spend);
                $conversion = $this->exchange->execute(
                    $request->user,
                    $quote,
                    "{$request->idempotencyKey}:convert:{$leg->asset->id}",
                );

                $legs[] = new SpendLeg(
                    $leg->asset,
                    $leg->spend,
                    $settlement->money((string) $quote->to_amount),
                    true,
                    $conversion->id,
                );
            }

            // 5. Settle: debit the (now sufficient) settlement balance into the
            //    caller's destination. One balanced, idempotent entry.
            $settlementAvailable = $this->accounts->forUser($request->user, LedgerAccountType::UserAvailable, $settlement->id);
            $lines = array_merge(
                [PostingLine::debit($settlementAvailable->id, $settlement->id, $required)],
                $request->destination,
            );

            $entry = $this->ledger->post(new EntryData(
                type: $request->purpose->ledgerType(),
                idempotencyKey: $request->idempotencyKey,
                lines: $lines,
                memo: $request->memo,
                metadata: array_merge($request->metadata, [
                    'spend_purpose' => $request->purpose->value,
                    'settlement_asset' => $settlement->symbol,
                    'settlement_amount' => $required->baseString(),
                    'funding' => array_map(fn (SpendLeg $l) => $l->toArray(), $legs),
                ]),
            ));

            ActivityLogger::log("spend.{$request->purpose->value}", null, [
                'user_id' => $request->user->id,
                'amount' => $required->toDecimal(),
                'settlement_asset' => $settlement->symbol,
                'legs' => array_map(fn (SpendLeg $l) => $l->toArray(), $legs),
            ], actor: $request->user);

            FundsSpent::dispatch($request->user->id, $entry->id, $request->purpose->value);

            return new SpendResult($entry, $legs, $required);
        });
    }
}
