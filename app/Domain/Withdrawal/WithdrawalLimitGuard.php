<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal;

use App\Domain\Exchange\UsdValuation;
use App\Enums\KycTier;
use App\Enums\WithdrawalStatus;
use App\Models\Asset;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\KycPolicy;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the per-KYC-tier rolling-24h withdrawal ceiling (§10.1). Previously
 * the ceiling lived in {@see KycTier} but was never enforced; it is
 * now an admin-configurable setting and checked here before funds are reserved.
 * A ceiling of 0 means unlimited for a withdrawable tier.
 */
class WithdrawalLimitGuard
{
    public function __construct(private readonly UsdValuation $usd) {}

    /**
     * @throws ValidationException when this withdrawal would breach the tier's daily USD ceiling
     */
    public function assertWithinTierCeiling(User $user, Asset $asset, Money $amount): void
    {
        $ceilingMinor = BigDecimal::of(KycPolicy::dailyWithdrawalCeilingMinor($user->tier()));
        if ($ceilingMinor->isLessThanOrEqualTo(0)) {
            return; // unlimited
        }

        $usedMinor = $this->spentTodayUsdMinor($user);
        $incomingMinor = BigDecimal::of($this->usd->toUsdMinor($asset, $amount));

        if ($usedMinor->plus($incomingMinor)->isGreaterThan($ceilingMinor)) {
            $ceilingUsd = $ceilingMinor->dividedBy(100, 2, RoundingMode::DOWN);
            throw ValidationException::withMessages([
                'amount' => "This withdrawal would exceed your daily limit of {$ceilingUsd} USD. Upgrade your verification to raise it.",
            ]);
        }
    }

    /** Sum of the user's still-standing withdrawals in the last 24h, in USD minor units. */
    private function spentTodayUsdMinor(User $user): BigDecimal
    {
        $withdrawals = Withdrawal::with('asset')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->whereNotIn('status', [WithdrawalStatus::Failed->value, WithdrawalStatus::Cancelled->value])
            ->get();

        $total = BigDecimal::zero();
        foreach ($withdrawals as $w) {
            if (! $w->asset) {
                continue;
            }
            $total = $total->plus($this->usd->toUsdMinor($w->asset, $w->asset->money($w->amount)));
        }

        return $total;
    }
}
