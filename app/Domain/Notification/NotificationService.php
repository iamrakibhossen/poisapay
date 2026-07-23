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
    public function __construct(private readonly NotificationTransportManager $transports) {}

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

        // Channels this template targets (defaults cover a template-less send).
        $targeted = $template?->channels ?? ['in_app', 'email'];

        $channels = $this->channelsFor($user, $category, $targeted);
        $extras = $this->transportChannelsFor($user, $category, $targeted);
        if ($channels === [] && $extras === []) {
            return;
        }

        $title = $rendered['subject'] ?: $key;
        $link = $url ?? ($data['url'] ?? null);

        if ($channels !== []) {
            $user->notify(new UserNotification(
                title: $title,
                body: $rendered['body'],
                url: $link,
                category: $category,
                channels: $channels,
            ));
        }

        // SMS / push go through swappable transports (stub logs; real vendors send).
        foreach ($extras as $channel) {
            $this->transports->dispatch($channel, $user, $title, $rendered['body'], $link);
        }
    }

    /**
     * Resolve Laravel channel names from the user's preference row for this
     * category, intersected with the template's targeted channels.
     *
     * @param  array<int, string>  $targeted
     * @return array<int, string>
     */
    private function channelsFor(User $user, string $category, array $targeted): array
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

    /**
     * SMS/push channels the user opted into for this category AND the template
     * targets. Delivered via NotificationTransportManager (not Laravel channels).
     *
     * @param  array<int, string>  $targeted
     * @return array<int, string>
     */
    private function transportChannelsFor(User $user, string $category, array $targeted): array
    {
        $pref = NotificationPreference::firstOrNew(
            ['user_id' => $user->id, 'category' => $category],
        );

        $extras = [];
        foreach (['sms', 'push', 'whatsapp', 'telegram'] as $channel) {
            $enabled = $pref->exists ? (bool) ($pref->{$channel} ?? false) : ($channel === 'push');
            if ($enabled && in_array($channel, $targeted, true)) {
                $extras[] = $channel;
            }
        }

        return $extras;
    }
}
