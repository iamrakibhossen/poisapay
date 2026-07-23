<?php

declare(strict_types=1);

namespace App\Domain\Ramp;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ramp\Contracts\PayoutProcessor;
use App\Domain\Ramp\DTO\PayoutRequest;
use App\Enums\RampDirection;
use App\Enums\RampRail;
use App\Enums\RampStatus;
use App\Models\Asset;
use App\Models\RampOrder;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Request a fiat off-ramp / cash-out (TDD §F1.3). Mirrors the withdrawal
 * reserve-before-send pattern: funds are locked in the ledger BEFORE the PSP is
 * instructed, so a failed payout simply releases the hold and nothing leaks.
 * Idempotent by client key. Terminal settlement arrives on the PSP webhook and
 * is applied by {@see SettleOffRampAction}.
 */
class RequestOffRampAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PayoutProcessor $psp,
    ) {}

    /**
     * @param  array<string, mixed>  $details  rail-specific payout destination fields
     */
    public function execute(
        User $user,
        Asset $fiat,
        Money $amount,
        RampRail $rail,
        string $idempotencyKey,
        ?string $beneficiary = null,
        array $details = [],
    ): RampOrder {
        if ($user->is_frozen) {
            throw new RuntimeException('Account is frozen.');
        }
        if (! $user->tier()->canWithdraw()) {
            throw ValidationException::withMessages([
                'kyc' => 'Your account tier does not permit cash-outs. Complete KYC to continue.',
            ]);
        }
        if ($this->ledger->availableBalance($user, $fiat->id)->isLessThan($amount)) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient balance for this cash-out.',
            ]);
        }

        return DB::transaction(function () use ($user, $fiat, $amount, $rail, $idempotencyKey, $beneficiary, $details): RampOrder {
            $existing = RampOrder::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $order = RampOrder::create([
                'user_id' => $user->id,
                'direction' => RampDirection::Off,
                'rail' => $rail,
                'fiat_asset_id' => $fiat->id,
                'fiat_amount' => $amount->baseString(),
                'beneficiary' => $beneficiary,
                'status' => RampStatus::Pending,
                'idempotency_key' => $idempotencyKey,
            ]);

            // RESERVE FIRST: available -> locked, before the PSP is touched.
            $this->ledger->lock(
                user: $user,
                assetId: $fiat->id,
                amount: $amount,
                idempotencyKey: "offramp:lock:{$order->id}",
                type: 'ramp.off.lock',
                metadata: ['ramp_order_id' => $order->id],
            );

            $result = $this->psp->initiatePayout(new PayoutRequest(
                orderId: $order->id,
                currency: $fiat->currency_code ?? $fiat->symbol,
                amount: $amount->baseString(),
                rail: $rail->value,
                beneficiary: $beneficiary,
                details: $details,
            ));

            if ($result->failed()) {
                // Release the hold immediately; nothing left the platform.
                $this->ledger->unlock(
                    user: $user,
                    assetId: $fiat->id,
                    amount: $amount,
                    idempotencyKey: "offramp:unlock:{$order->id}",
                    type: 'ramp.off.unlock',
                    metadata: ['ramp_order_id' => $order->id],
                );
                $order->update(['status' => RampStatus::Failed, 'provider_ref' => $result->providerRef]);

                return $order->refresh();
            }

            $order->update(['status' => RampStatus::Confirmed, 'provider_ref' => $result->providerRef]);

            ActivityLogger::log('ramp.offramp.requested', $order, ['amount' => $order->fiat_amount]);

            return $order->refresh();
        });
    }
}
