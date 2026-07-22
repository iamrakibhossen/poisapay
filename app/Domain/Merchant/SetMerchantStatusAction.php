<?php

declare(strict_types=1);

namespace App\Domain\Merchant;

use App\Domain\Audit\ActivityLogger;
use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Notifications\OperatorNotification;

/**
 * Operator control over a merchant's lifecycle (TDD §8/§9): approve a pending
 * application, suspend an active merchant (blocks new payments), or reinstate.
 */
class SetMerchantStatusAction
{
    public function execute(Merchant $merchant, MerchantStatus $status, ?string $reason = null): Merchant
    {
        $merchant->update([
            'status' => $status,
            'suspension_reason' => $status === MerchantStatus::Suspended ? $reason : null,
            'approved_at' => $status === MerchantStatus::Active && ! $merchant->approved_at ? now() : $merchant->approved_at,
        ]);

        ActivityLogger::log("merchant.{$status->value}", $merchant, ['reason' => $reason]);

        $merchant->user?->notify(new OperatorNotification(
            title: 'Merchant status updated',
            body: "Your merchant account is now {$status->value}.".($reason ? " Reason: {$reason}" : ''),
            category: 'merchant',
        ));

        return $merchant->refresh();
    }
}
