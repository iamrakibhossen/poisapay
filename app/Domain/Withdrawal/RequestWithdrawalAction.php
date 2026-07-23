<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Compliance\TransactionMonitor;
use App\Domain\Compliance\TravelRule\TravelRuleService;
use App\Domain\Fees\PlatformFees;
use App\Domain\Ledger\LedgerService;
use App\Domain\Risk\RiskEngine;
use App\Domain\Security\AddressBookService;
use App\Domain\Security\VelocityGuard;
use App\Enums\WithdrawalStatus;
use App\Events\WithdrawalRequested;
use App\Models\Asset;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Withdrawal request (TDD §6.3). Reserve-before-sign (A3): the ledger locks the
 * funds BEFORE anything touches the chain. Risk scoring decides auto-approve vs
 * manual admin review (§10.3). Idempotent by client key.
 */
class RequestWithdrawalAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly RiskEngine $risk,
        private readonly TransactionMonitor $monitor,
        private readonly AddressBookService $addressBook,
        private readonly VelocityGuard $velocity,
        private readonly TravelRuleService $travelRule,
    ) {}

    /**
     * @param  string|null  $payoutMethod  'bank'|'mobile' for a fiat cash-out; null for an on-chain crypto withdrawal
     * @param  array<string, mixed>  $payoutDetails  bank/mobile account details for a fiat cash-out
     * @param  Money|null  $feeOverride  method-specific fee (e.g. a fiat payout rail); defaults to the asset's withdrawal_fee
     */
    public function execute(
        User $user,
        Asset $asset,
        Money $amount,
        string $toAddress,
        string $idempotencyKey,
        ?string $payoutMethod = null,
        array $payoutDetails = [],
        ?Money $feeOverride = null,
    ): Withdrawal {
        // Compliance gate: tier must permit withdrawals (§10.1).
        if (! $user->tier()->canWithdraw()) {
            throw ValidationException::withMessages([
                'kyc' => 'Your account tier does not permit withdrawals. Complete KYC to continue.',
            ]);
        }
        if ($user->is_frozen) {
            throw new RuntimeException('Account is frozen.');
        }

        // Minimum amount policy.
        $min = $asset->money($asset->withdrawal_min);
        if ($amount->isLessThan($min)) {
            throw ValidationException::withMessages([
                'amount' => "Minimum withdrawal is {$min->format()}.",
            ]);
        }

        // Address whitelist (on-chain destinations only; feature-gated, no-op when off).
        if ($payoutMethod === null) {
            $this->addressBook->assertWithdrawable($user, $toAddress, $asset->chain_id);
        }

        return DB::transaction(function () use ($user, $asset, $amount, $toAddress, $idempotencyKey, $payoutMethod, $payoutDetails, $feeOverride): Withdrawal {
            $existing = Withdrawal::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $assessment = $this->risk->scoreWithdrawal($user, $amount, $toAddress);

            // Velocity limiting: breaching the rolling-24h cap forces manual review.
            $velocityHit = $this->velocity->exceededWithdrawalVelocity($user);
            $mustReview = $assessment->requiresManualReview() || $velocityHit;

            // Rail fee (flat network cost or fiat method fee) + platform % (admin's cut).
            $railFee = $feeOverride ?? $asset->money($asset->withdrawal_fee);
            $fee = $railFee->plus($asset->money(PlatformFees::withdrawalFee($amount->baseString())));

            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'asset_id' => $asset->id,
                'to_address' => $toAddress,
                'payout_method' => $payoutMethod,
                'payout_details' => $payoutMethod ? $payoutDetails : null,
                'amount' => $amount->baseString(),
                'fee' => $fee->baseString(),
                'status' => $mustReview ? WithdrawalStatus::Review : WithdrawalStatus::Approved,
                'idempotency_key' => $idempotencyKey,
                'risk_score' => $assessment->score,
                'risk_level' => $assessment->level,
                'requires_review' => $mustReview,
            ]);

            // AML monitoring: screen + raise alerts. A sanctions hit or critical
            // score forces the withdrawal into manual review before it can settle.
            if ($this->monitor->inspectWithdrawal($withdrawal, $assessment)
                && $withdrawal->status !== WithdrawalStatus::Review) {
                $withdrawal->update(['status' => WithdrawalStatus::Review, 'requires_review' => true]);
            }

            // RESERVE FIRST: lock available -> locked (§6.3 step 3). Total moved = amount + fee.
            $total = $amount->plus($fee);
            $lockEntry = $this->ledger->lock(
                user: $user,
                assetId: $asset->id,
                amount: $total,
                idempotencyKey: "withdrawal:lock:{$withdrawal->id}",
                type: 'withdrawal.lock',
                metadata: ['withdrawal_id' => $withdrawal->id],
            );

            $withdrawal->update(['lock_entry_id' => $lockEntry->id]);

            // Travel Rule (on-chain, above threshold): capture originator/beneficiary.
            if ($payoutMethod === null && $this->travelRule->applies($asset, $amount)) {
                $this->travelRule->recordForWithdrawal($withdrawal, $user, $asset);
            }

            ActivityLogger::log('withdrawal.requested', $withdrawal, ['amount' => $withdrawal->amount]);

            WithdrawalRequested::dispatch($withdrawal->id);

            return $withdrawal->refresh();
        });
    }
}
