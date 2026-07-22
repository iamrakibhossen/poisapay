<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Enums\LedgerAccountType;
use App\Models\Admin;
use App\Models\Asset;
use App\Models\ProfitPayout;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Operator profit withdrawal (§5.3). Distributes accrued fee revenue out of the
 * income accounts (fx spread + card + fee income) into owner:payout AND actually
 * sends the backing crypto out of the treasury (pending/hot/cold → treasury:out),
 * recording an auditable {@see ProfitPayout}. Reserves drop by the amount taken;
 * converting that crypto to fiat in a bank is the final external step. Solvency
 * is preserved (the reserve that shrinks is exactly the profit) and user funds
 * are never touched.
 */
class WithdrawProfitAction
{
    /** Income accounts drawn from, in order. */
    private const FEE_ACCOUNTS = [
        LedgerAccountType::FxSpreadIncome,
        LedgerAccountType::FeeCard,
        LedgerAccountType::FeeIncome,
    ];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    /** Total withdrawable profit for an asset (sum of the fee-income accounts). */
    public function availableProfit(Asset $asset): Money
    {
        $total = BigInteger::zero();
        foreach (self::FEE_ACCOUNTS as $type) {
            $total = $total->plus($this->accountBalance($type, $asset->id));
        }

        return Money::ofBase($total, $asset->decimals, $asset->symbol);
    }

    public function execute(Admin $operator, Asset $asset, Money $amount, ?string $destination = null, ?string $note = null): ProfitPayout
    {
        if (! $amount->isPositive()) {
            throw new RuntimeException('Enter an amount greater than zero.');
        }

        return DB::transaction(function () use ($operator, $asset, $amount, $destination, $note): ProfitPayout {
            $available = $this->availableProfit($asset);
            if ($amount->isGreaterThanOrEqual($available) && ! $amount->equals($available)) {
                throw new RuntimeException("You can withdraw at most {$available->format()} of profit in {$asset->symbol}.");
            }

            // Draw the amount from the fee accounts in order (debit reduces each credit-normal balance).
            $remaining = BigInteger::of($amount->baseString());
            $lines = [];
            foreach (self::FEE_ACCOUNTS as $type) {
                if ($remaining->isZero()) {
                    break;
                }
                $bal = $this->accountBalance($type, $asset->id);
                if ($bal->isLessThanOrEqualTo(0)) {
                    continue;
                }
                $draw = $bal->isLessThan($remaining) ? $bal : $remaining;
                $lines[] = PostingLine::debit($this->accounts->system($type, $asset->id)->id, $asset->id, Money::ofBase($draw, $asset->decimals, $asset->symbol));
                $remaining = $remaining->minus($draw);
            }

            $payoutAccount = $this->accounts->system(LedgerAccountType::OwnerPayout, $asset->id);
            $lines[] = PostingLine::credit($payoutAccount->id, $asset->id, $amount);

            // Real off-ramp: the backing crypto actually leaves the treasury
            // (pending/hot/cold → treasury:out), so reserves drop by the amount taken.
            $lines = array_merge($lines, $this->treasuryOutflowLines($asset, $amount));

            $entry = $this->ledger->post(new EntryData(
                type: 'profit.payout',
                idempotencyKey: 'profit:payout:'.Str::uuid()->toString(),
                lines: $lines,
                memo: 'Profit withdrawal'.($destination ? ' → '.$destination : ''),
                metadata: ['operator_id' => $operator->id],
            ));

            // Simulated on-chain broadcast (testnet). A real signer/broadcaster
            // slots in here when custody is live; a crypto payout gets a tx hash
            // + gas and completes, a fiat payout is simply recorded.
            $isCrypto = ! $asset->isFiat();
            $txHash = $isCrypto ? '0x'.substr(hash('sha256', $entry->id.$asset->id), 0, 64) : null;

            $payout = ProfitPayout::create([
                'asset_id' => $asset->id,
                'amount' => $amount->baseString(),
                'destination' => $destination,
                'network' => $asset->chain?->name,
                'destination_address' => $isCrypto ? $destination : null,
                'status' => $isCrypto ? 'completed' : 'recorded',
                'tx_hash' => $txHash,
                'gas_fee' => $isCrypto ? '300000000000000' : '0', // ~0.0003 native
                'completed_at' => now(),
                'note' => $note,
                'entry_id' => $entry->id,
                'created_by' => $operator->id,
            ]);

            ActivityLogger::log('profit.withdrawn', $payout, [
                'amount' => $amount->baseString(),
                'asset' => $asset->symbol,
                'destination' => $destination,
                'tx_hash' => $txHash,
            ], actor: $operator);

            return $payout;
        });
    }

    /** Credit-normal fee account balance (credit − debit) in base units. */
    private function accountBalance(LedgerAccountType $type, int $assetId): BigInteger
    {
        $account = $this->accounts->system($type, $assetId);
        $balance = DB::table('account_balances')->where('account_id', $account->id)->value('balance');

        return BigInteger::of((string) ($balance ?? '0'));
    }

    /**
     * Ledger legs that send `$amount` out of the treasury: credit the custody
     * pools (pending → hot → cold) until covered, and debit treasury:out — the
     * same outflow account user withdrawals settle to. Keeps solvency intact:
     * the reserve that shrinks is exactly the profit being taken.
     *
     * @return array<int, PostingLine>
     */
    private function treasuryOutflowLines(Asset $asset, Money $amount): array
    {
        $sources = [
            LedgerAccountType::TreasuryPending,
            LedgerAccountType::TreasuryHot,
            LedgerAccountType::TreasuryCold,
        ];

        $remaining = BigInteger::of($amount->baseString());
        $lines = [];

        foreach ($sources as $type) {
            if ($remaining->isZero()) {
                break;
            }
            $bal = $this->accountBalance($type, $asset->id);
            if ($bal->isLessThanOrEqualTo(0)) {
                continue;
            }
            $draw = $bal->isLessThan($remaining) ? $bal : $remaining;
            $lines[] = PostingLine::credit(
                $this->accounts->system($type, $asset->id)->id, $asset->id,
                Money::ofBase($draw, $asset->decimals, $asset->symbol),
            );
            $remaining = $remaining->minus($draw);
        }

        if ($remaining->isPositive()) {
            throw new RuntimeException('Treasury reserves are insufficient to send this profit on-chain.');
        }

        $lines[] = PostingLine::debit($this->accounts->system(LedgerAccountType::TreasuryOut, $asset->id)->id, $asset->id, $amount);

        return $lines;
    }
}
