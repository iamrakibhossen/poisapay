<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic user-facing notification (§F4). The delivery channels are decided by
 * {@see NotificationService} from the user's per-category
 * preferences, so this class simply honours whatever channel list it is handed.
 */
class UserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<int, string>  $channels  Laravel channel names (database/mail/broadcast) */
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $category = 'product',
        public array $channels = ['database'],
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->channels ?: ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'category' => $this->category,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title)->line($this->body);
        if ($this->url) {
            $mail->action('View', url($this->url));
        }

        return $mail;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
