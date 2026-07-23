<?php

declare(strict_types=1);

namespace App\Domain\Compliance;

use App\Models\User;
use RuntimeException;

/**
 * Central account-status gate (Wave 5). A frozen account must not move value at
 * ANY touchpoint — withdrawals, off-ramp and card auth already guard inline; this
 * gives transfers, exchange and merchant payments the same enforcement from one
 * place so no money-movement path can be missed.
 */
final class AccountGuard
{
    public static function assertActive(User $user): void
    {
        if ($user->is_frozen) {
            throw new RuntimeException('Account is frozen.');
        }
    }
}
