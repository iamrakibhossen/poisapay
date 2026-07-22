<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\UserNotification;

/**
 * User notification dispatcher (§F4). Resolves an admin-editable template for the
 * event key, renders it with the payload, and delivers only over the channels the
 * user has opted into for that category. In-app (database) is always delivered so
 * the notification centre stays complete; email/sms/push honour preferences and
 * the template's own channel targeting. Security-category messages ignore opt-outs.
 *
 * Channel mapping: in_app -> database + broadcast (live bell), email -> mail.
 * SMS/push are recorded as preferences today and become real transports when a
 * provider is wired in — no code here changes when that happens.
 */
class NotificationService
{
    /**
     * @param  array<string, mixed>  $data  template placeholders + optional 'url'
     */
    public function send(User $user, string $key, array $data = [], ?string $category = null, ?string $url = null): void
    {
        $template = NotificationTemplate::resolve($key, $user->locale ?? 'en');

        $category ??= $template?->category ?? 'product';
        $rendered = $template
            ? $template->render($data)
            : ['subject' => $data['title'] ?? $key, 'body' => $data['body'] ?? ''];

        $channels = $this->channelsFor($user, $category, $template);
        if ($channels === []) {
            return;
        }

        $user->notify(new UserNotification(
            title: $rendered['subject'] ?: $key,
            body: $rendered['body'],
            url: $url ?? ($data['url'] ?? null),
            category: $category,
            channels: $channels,
        ));
    }

    /**
     * Resolve Laravel channel names from the user's preference row for this
     * category, intersected with the template's targeted channels.
     *
     * @return array<int, string>
     */
    private function channelsFor(User $user, string $category, ?NotificationTemplate $template): array
    {
        $pref = NotificationPreference::firstOrNew(
            ['user_id' => $user->id, 'category' => $category],
        );
        // Sensible defaults for an unseen category.
        $inApp = $pref->exists ? $pref->in_app : true;
        $email = $pref->exists ? $pref->email : true;

        // Security notifications are mandatory — never suppressed by preferences.
        if ($category === 'security') {
            $inApp = $email = true;
        }

        $targeted = $template?->channels ?? ['in_app', 'email'];
        $channels = [];

        if ($inApp && in_array('in_app', $targeted, true)) {
            $channels[] = 'database';
            $channels[] = 'broadcast';
        }
        if ($email && in_array('email', $targeted, true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
