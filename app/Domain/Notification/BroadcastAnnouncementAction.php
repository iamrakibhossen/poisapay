<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Domain\Audit\ActivityLogger;
use App\Enums\KycTier;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Operator announcement broadcast (§F4/§9). Sends a one-off message to a user
 * segment via the in-app bell (and email when requested), records the send and
 * its recipient count. Delivered in chunks so a large audience never buffers.
 */
class BroadcastAnnouncementAction
{
    public function execute(Admin $operator, string $title, string $body, string $segment = 'all', array $channels = ['in_app'], string $category = 'product'): Announcement
    {
        $laravelChannels = [];
        if (in_array('in_app', $channels, true)) {
            $laravelChannels[] = 'database';
            $laravelChannels[] = 'broadcast';
        }
        if (in_array('email', $channels, true)) {
            $laravelChannels[] = 'mail';
        }

        $count = 0;
        $notification = new UserNotification($title, $body, null, $category, $laravelChannels ?: ['database']);

        $this->segmentQuery($segment)->chunkById(500, function ($users) use ($notification, &$count) {
            Notification::send($users, $notification);
            $count += $users->count();
        });

        $announcement = Announcement::create([
            'title' => $title,
            'body' => $body,
            'segment' => $segment,
            'category' => $category,
            'channels' => $channels,
            'recipients' => $count,
            'sent_by' => $operator->id,
            'sent_at' => now(),
        ]);

        ActivityLogger::log('announcement.sent', $announcement, ['segment' => $segment, 'recipients' => $count], actor: $operator);

        return $announcement;
    }

    private function segmentQuery(string $segment)
    {
        return match ($segment) {
            'kyc_full' => User::query()->where('kyc_tier', KycTier::Full->value),
            'merchants' => User::query()->whereHas('merchant'),
            default => User::query(),
        };
    }
}
