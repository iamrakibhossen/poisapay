<?php

declare(strict_types=1);

namespace App\Shop\Listeners;

use App\Domain\Notification\AdminNotifier;
use App\Shop\Events\SellerApplied;

/** A new/re-submitted seller application needs operator review — alert the operators. */
class NotifyOperatorsOfSellerApplication
{
    public function __construct(private readonly AdminNotifier $notifier) {}

    public function handle(SellerApplied $event): void
    {
        $name = $event->seller->brand_name ?: $event->user->name;

        $this->notifier->notify(
            title: 'New seller application',
            body: "{$name} applied to sell and is awaiting review.",
            url: route('admin.sellers', ['status' => 'pending_review']),
            category: 'seller',
        );
    }
}
