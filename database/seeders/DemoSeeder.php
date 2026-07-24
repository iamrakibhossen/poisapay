<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Deposit\CreditManualDepositAction;
use App\Domain\Deposit\SubmitManualDepositAction;
use App\Domain\Exchange\ExchangeService;
use App\Domain\Exchange\ExecuteSwapAction;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Transfer\ExecuteTransferAction;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Domain\Withdrawal\SettleWithdrawalAction;
use App\Enums\ConversionContext;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\LedgerAccountType;
use App\Enums\WithdrawalStatus;
use App\Models\Asset;
use App\Models\Conversion;
use App\Models\DepositMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Throwable;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $ledger = app(LedgerService::class);
        $resolver = app(AccountResolver::class);

        // Demo consumer with funded wallets. (Operators live in the `admins`
        // table — see AdminSeeder — not here.)
        $demo = User::updateOrCreate(
            ['email' => 'demo@poisapay.test'],
            [
                'name' => 'Rahim Uddin',
                'phone' => '+8801700000000',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'kyc_tier' => KycTier::Full,
                'kyc_status' => KycStatus::Approved,
                'referral_code' => 'RAHIM123',
                'base_currency' => 'BDT',
            ],
        );

        $balances = [
            'USDT' => '1250000000',        // 1,250 USDT (6dp)
            'ETH' => '850000000000000000', // 0.85 ETH
            'TRX' => '4200000000000000000000', // 4,200 TRX
            'BDT' => '4550000',            // 45,500.00 BDT (2dp)
        ];

        foreach ($balances as $symbol => $base) {
            $asset = Asset::where('symbol', $symbol)->first();
            if (! $asset) {
                continue;
            }
            $resolver->ensureSystemAccounts($asset->id);
            $treasury = $resolver->system(LedgerAccountType::TreasuryPending, $asset->id);
            $available = $resolver->forUser($demo, LedgerAccountType::UserAvailable, $asset->id);

            $ledger->post(new EntryData(
                type: 'seed.credit',
                idempotencyKey: 'seed:'.$demo->id.':'.$asset->id,
                lines: [
                    PostingLine::debit($treasury->id, $asset->id, $base),
                    PostingLine::credit($available->id, $asset->id, $base),
                ],
                memo: 'Seed balance',
            ));
        }

        // A few more consumers for admin lists (and transfer counterparties).
        $others = User::factory()->count(12)->create();

        // Give the demo account a realistic history across every module so the
        // history pages, "View all" links and the withdrawal progress tracker
        // aren't empty on a fresh seed. Idempotent: skip once activity exists.
        if (! Conversion::where('user_id', $demo->id)->exists()) {
            $this->seedActivity($demo, $others->take(3)->all());
        }
    }

    /** @param  array<int, User>  $others */
    private function seedActivity(User $demo, array $others): void
    {
        $this->seedSwaps($demo);
        $this->seedTransfers($demo, $others);
        $this->seedDeposits($demo);
        $this->seedWithdrawals($demo);
    }

    /** Swaps out of USDT into the coins with liquidity — populates exchange + wallet activity. */
    private function seedSwaps(User $demo): void
    {
        $usdt = Asset::where('symbol', 'USDT')->orderBy('id')->first();
        if (! $usdt) {
            return;
        }

        foreach (['ETH' => '40', 'TRX' => '25', 'BTC' => '30', 'BNB' => '20'] as $symbol => $amount) {
            $to = Asset::where('symbol', $symbol)->orderBy('id')->first();
            if (! $to) {
                continue;
            }
            $this->attempt("swap USDT→{$symbol}", function () use ($demo, $usdt, $to, $amount) {
                $quote = app(ExchangeService::class)->quote(
                    $demo, $usdt, $to, Money::ofDecimal($amount, $usdt->decimals, $usdt->symbol), ConversionContext::Swap
                );
                app(ExecuteSwapAction::class)->execute($demo, $quote->fresh(['fromAsset', 'toAsset']));
            });
        }
    }

    /** Sent + received transfers — populates the send history and wallet activity. */
    private function seedTransfers(User $demo, array $others): void
    {
        $usdt = Asset::where('symbol', 'USDT')->orderBy('id')->first();
        if (! $usdt || count($others) < 2) {
            return;
        }
        [$a, $b] = [$others[0], $others[1]];

        // Demo sends to two people.
        foreach ([[$a, '15', 'Lunch split'], [$b, '8.5', 'Thanks!']] as $i => [$recipient, $amount, $memo]) {
            $this->attempt("transfer sent #{$i}", fn () => app(ExecuteTransferAction::class)->execute(
                $demo, $recipient, $usdt, Money::ofDecimal($amount, $usdt->decimals, $usdt->symbol), 'seed-send:'.$demo->id.':'.$i, $memo
            ));
        }

        // Someone sends to demo (received) — fund the sender first so the ledger balances.
        $this->attempt('transfer received', function () use ($demo, $a, $usdt) {
            $resolver = app(AccountResolver::class);
            app(LedgerService::class)->post(new EntryData(
                type: 'seed.credit',
                idempotencyKey: 'seed-counterparty:'.$a->id,
                lines: [
                    PostingLine::debit($resolver->system(LedgerAccountType::TreasuryPending, $usdt->id)->id, $usdt->id, '60000000'),
                    PostingLine::credit($resolver->forUser($a, LedgerAccountType::UserAvailable, $usdt->id)->id, $usdt->id, '60000000'),
                ],
                memo: 'Seed counterparty balance',
            ));
            app(ExecuteTransferAction::class)->execute(
                $a, $demo, $usdt, Money::ofDecimal('12', $usdt->decimals, $usdt->symbol), 'seed-recv:'.$demo->id, 'Repayment'
            );
        });
    }

    /** Manual deposits — one pending, the rest credited — populate the deposit history. */
    private function seedDeposits(User $demo): void
    {
        $method = DepositMethod::with('asset')->where('is_active', true)
            ->whereHas('asset', fn ($q) => $q->where('is_active', true))->orderBy('id')->first();
        if (! $method) {
            return;
        }

        foreach (['500', '1200', '300'] as $i => $amount) {
            $this->attempt("deposit #{$i}", function () use ($demo, $method, $amount, $i) {
                $deposit = app(SubmitManualDepositAction::class)->execute(
                    $demo, $method, Money::ofDecimal($amount, $method->asset->decimals, $method->asset->symbol), 'SEED-DEP-'.$i
                );
                // Leave the last one pending to show a non-credited row.
                if ($i < 2) {
                    app(CreditManualDepositAction::class)->execute($deposit);
                }
            });
        }
    }

    /**
     * Withdrawals across the lifecycle so the detail modal's progress tracker has
     * something to show at each stage: Completed, Sent (broadcast), Approved, Pending.
     */
    private function seedWithdrawals(User $demo): void
    {
        $trx = Asset::where('symbol', 'TRX')->orderBy('id')->first();
        if (! $trx) {
            return;
        }
        $addr = 'TJRabPrwbZy45sbavfcjinPJC18kjpRTv8'; // a well-formed TRON address

        $stages = [
            ['100', WithdrawalStatus::Completed],
            ['75', WithdrawalStatus::Broadcast],
            ['50', WithdrawalStatus::Approved],
            ['30', WithdrawalStatus::Pending],
        ];

        foreach ($stages as $i => [$amount, $status]) {
            $this->attempt("withdrawal {$status->value}", function () use ($demo, $trx, $addr, $amount, $status, $i) {
                $w = app(RequestWithdrawalAction::class)->execute(
                    $demo, $trx, Money::ofDecimal($amount, $trx->decimals, $trx->symbol), $addr, 'seed-wd:'.$demo->id.':'.$i
                );

                if ($status === WithdrawalStatus::Completed) {
                    app(SettleWithdrawalAction::class)->execute($w, str_repeat('0', 8).'seed'.$i.str_repeat('a', 52));
                } elseif ($status !== $w->status) {
                    // Nudge the seeded row to the target stage for tracker variety.
                    $w->forceFill(['status' => $status])->save();
                }
            });
        }
    }

    /** Run a seed step, warning (not failing) if a guard/rate/liquidity check rejects it. */
    private function attempt(string $label, callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->command?->warn("  demo activity skipped ({$label}): {$e->getMessage()}");
        }
    }
}
