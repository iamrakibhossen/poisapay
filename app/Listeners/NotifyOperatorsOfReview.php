<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Notification\AdminNotifier;
use App\Events\KycSubmitted;
use App\Events\WithdrawalRequested;
use App\Models\KycProfile;
use App\Models\Withdrawal;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Alert operators to items needing manual review (§10 gates). */
class NotifyOperatorsOfReview implements ShouldQueue
{
    public function __construct(private readonly AdminNotifier $notifier) {}

    public function handleWithdrawal(WithdrawalRequested $event): void
    {
        $w = Withdrawal::with('user', 'asset')->find($event->withdrawalId);
        if (! $w || ! $w->requires_review) {
            return;
        }

        $this->notifier->notify(
            'Withdrawal awaiting review',
            "{$w->user->name} requested {$w->money()->format()} ({$w->risk_level->label()} risk).",
            route('admin.withdrawals'),
            'withdrawal',
        );
    }

    public function handleKyc(KycSubmitted $event): void
    {
        $profile = KycProfile::with('user')->find($event->profileId);
        if (! $profile) {
            return;
        }

        $this->notifier->notify(
            'New KYC submission',
            "{$profile->user->name} submitted {$profile->requested_tier->label()} verification.",
            route('admin.kyc'),
            'kyc',
        );
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            WithdrawalRequested::class => 'handleWithdrawal',
            KycSubmitted::class => 'handleKyc',
        ];
    }
}
