<?php

declare(strict_types=1);

namespace App\Domain\Support;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Notification\NotificationService;
use App\Enums\SupportTicketStatus;
use App\Models\Admin;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Support-ticket workflow (Wave 6). Opens tickets with an initial message, threads
 * user + staff replies (staff replies aren't tied to a user row), toggles status,
 * and fans notifications: operators on a new ticket, the user on a staff reply.
 */
class SupportTicketService
{
    public function __construct(private readonly NotificationService $notify) {}

    public function open(User $user, string $subject, string $category, string $priority, string $body): SupportTicket
    {
        return DB::transaction(function () use ($user, $subject, $category, $priority, $body): SupportTicket {
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'status' => SupportTicketStatus::Open,
            ]);

            SupportMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $user->id,
                'author_name' => $user->name,
                'body' => $body,
                'is_staff' => false,
            ]);

            notifyAdmins(
                'New support ticket',
                "{$user->name}: {$subject}",
                route('admin.support.show', $ticket->id),
                'support',
            );
            ActivityLogger::log('support.ticket.opened', $ticket, ['subject' => $subject], actor: $user);

            return $ticket;
        });
    }

    public function userReply(SupportTicket $ticket, User $user, string $body): SupportMessage
    {
        $message = SupportMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $user->id,
            'author_name' => $user->name,
            'body' => $body,
            'is_staff' => false,
        ]);

        // A user reply re-opens the ticket for staff attention.
        $ticket->update(['status' => SupportTicketStatus::Open]);
        ActivityLogger::log('support.ticket.user_replied', $ticket, [], actor: $user);

        return $message;
    }

    public function staffReply(SupportTicket $ticket, Admin $admin, string $body): SupportMessage
    {
        $message = SupportMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => null,
            'author_name' => $admin->name,
            'body' => $body,
            'is_staff' => true,
        ]);

        $ticket->update([
            'status' => SupportTicketStatus::Pending, // awaiting the user
            'assigned_to' => $ticket->assigned_to ?? $admin->id,
        ]);

        $ticket->loadMissing('user');
        $this->notify->send($ticket->user, 'support.reply', [
            'title' => 'Support replied to your ticket',
            'body' => 'Our team replied to "'.$ticket->subject.'". Open the ticket to read and respond.',
        ], category: 'product', url: route('support.show', $ticket->id));

        ActivityLogger::log('support.ticket.staff_replied', $ticket, [], actor: $admin);

        return $message;
    }

    public function setStatus(SupportTicket $ticket, SupportTicketStatus $status, ?Admin $by = null): void
    {
        $ticket->update(['status' => $status]);
        ActivityLogger::log('support.ticket.status', $ticket, ['status' => $status->value], actor: $by);
    }

    public function assign(SupportTicket $ticket, Admin $admin): void
    {
        $ticket->update(['assigned_to' => $admin->id]);
    }
}
