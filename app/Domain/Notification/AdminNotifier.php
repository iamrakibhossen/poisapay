<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Models\Admin;
use App\Notifications\OperatorNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Fan a notification out to operator accounts (DollarHub AdminNotifier pattern).
 * Stored in the database + broadcast for the live bell.
 */
class AdminNotifier
{
    public function notify(string $title, string $body, ?string $url = null, string $category = 'general'): void
    {
        $admins = Admin::where('is_active', true)->get();
        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new OperatorNotification($title, $body, $url, $category));
    }
}
