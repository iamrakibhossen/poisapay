<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Notification\AdminNotifier;
use App\Events\DepositCredited;
use App\Events\InvoicePaid;
use App\Events\UserRegistered;
use App\Events\WithdrawalCompleted;
use App\Models\Deposit;
use App\Models\MerchantInvoice;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Operator activity feed (DollarHub AdminNotifier pattern). Fires a bell
 * notification to operators on the key lifecycle events — new registrations,
 * credited deposits, completed withdrawals and paid invoices — complementing the
 * review-queue alerts in {@see NotifyOperatorsOfReview}.
 */
class NotifyOperatorsOfActivity implements ShouldQueue
{
    public function __construct(private readonly AdminNotifier $notifier) {}

    public function handleRegistration(UserRegistered $event): void
    {
        $user = User::find($event->userId);
        if (! $user) {
            return;
        }

        $this->notifier->notify(
            'New registration',
            "{$user->name} ({$user->email}) just signed up.",
            route('admin.users'),
            'user',
        );
    }

    public function handleDeposit(DepositCredited $event): void
    {
        $deposit = Deposit::with('user', 'asset')->find($event->depositId);
        if (! $deposit) {
            return;
        }

        $this->notifier->notify(
            'Deposit credited',
            "{$deposit->user?->name} was credited {$deposit->money()->format()}.",
            route('admin.deposits'),
            'deposit',
        );
    }

    public function handleWithdrawalCompleted(WithdrawalCompleted $event): void
    {
        $withdrawal = Withdrawal::with('user', 'asset')->find($event->withdrawalId);
        if (! $withdrawal) {
            return;
        }

        $this->notifier->notify(
            'Withdrawal completed',
            "{$withdrawal->user?->name}'s withdrawal of {$withdrawal->money()->format()} completed.",
            route('admin.withdrawals'),
            'withdrawal',
        );
    }

    public function handleInvoicePaid(InvoicePaid $event): void
    {
        $invoice = MerchantInvoice::with('merchant', 'asset')->find($event->invoiceId);
        if (! $invoice) {
            return;
        }

        $this->notifier->notify(
            'Invoice paid',
            "{$invoice->merchant?->name} received {$invoice->money()->format()} (invoice {$invoice->reference}).",
            route('admin.merchants'),
            'merchant',
        );
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            UserRegistered::class => 'handleRegistration',
            DepositCredited::class => 'handleDeposit',
            WithdrawalCompleted::class => 'handleWithdrawalCompleted',
            InvoicePaid::class => 'handleInvoicePaid',
        ];
    }
}
