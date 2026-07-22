<?php

declare(strict_types=1);

namespace App\Domain\Rewards;

use App\Domain\Audit\ActivityLogger;
use App\Models\Admin;
use App\Models\Asset;
use App\Models\RewardGrant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Operator-initiated one-off reward grant (TDD §F5 / §9). A real treasury payout
 * through {@see GrantRewardAction}; every grant is uniquely keyed so it is a
 * deliberate, auditable action rather than an idempotent replay.
 */
class ManualGrantAction
{
    public function __construct(
        private readonly GrantRewardAction $grant,
    ) {}

    public function execute(Admin $operator, User $user, Asset $asset, Money $amount, ?string $reason = null): RewardGrant
    {
        $grant = $this->grant->execute(
            $user,
            'manual',
            $asset,
            $amount,
            'reward:manual:'.Str::uuid()->toString(),
        );

        ActivityLogger::log('reward.manual_grant', $grant, [
            'operator_id' => $operator->id,
            'amount' => $amount->baseString(),
            'reason' => $reason,
        ], actor: $operator);

        return $grant;
    }
}
