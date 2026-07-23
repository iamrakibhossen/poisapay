<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Support\SupportTicketService;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Operator support workbench (Wave 6): triage, reply to, assign, and close user
 * tickets. Controller + Blade (DollarHub structure), permission-gated.
 */
class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $this->guard('view-support');

        $status = (string) $request->query('status', 'active');

        $tickets = SupportTicket::query()
            ->with(['user', 'assignedTo'])
            ->withCount('messages')
            ->when($status === 'active', fn ($q) => $q->whereIn('status', [SupportTicketStatus::Open->value, SupportTicketStatus::Pending->value]))
            ->when(in_array($status, ['open', 'pending', 'resolved', 'closed'], true), fn ($q) => $q->where('status', $status))
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.support.index', [
            'tickets' => $tickets,
            'status' => $status,
            'stats' => [
                'open' => SupportTicket::where('status', SupportTicketStatus::Open->value)->count(),
                'pending' => SupportTicket::where('status', SupportTicketStatus::Pending->value)->count(),
            ],
        ]);
    }

    public function show(Request $request, string $id): View
    {
        $this->guard('view-support');

        $ticket = SupportTicket::with(['user', 'assignedTo', 'messages' => fn ($q) => $q->orderBy('created_at')])
            ->findOrFail($id);

        return view('admin.support.show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, string $id, SupportTicketService $service): RedirectResponse
    {
        $this->guard('manage-support');
        $ticket = SupportTicket::findOrFail($id);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $service->staffReply($ticket, auth('admin')->user(), $data['body']);

        return back()->with('status', 'Reply sent to the user.');
    }

    public function updateStatus(Request $request, string $id, SupportTicketService $service): RedirectResponse
    {
        $this->guard('manage-support');
        $ticket = SupportTicket::findOrFail($id);
        $data = $request->validate(['status' => ['required', 'in:open,pending,resolved,closed']]);

        $service->setStatus($ticket, SupportTicketStatus::from($data['status']), auth('admin')->user());

        return back()->with('status', 'Ticket status updated.');
    }

    public function assign(Request $request, string $id, SupportTicketService $service): RedirectResponse
    {
        $this->guard('manage-support');
        $ticket = SupportTicket::findOrFail($id);
        $service->assign($ticket, auth('admin')->user());

        return back()->with('status', 'Ticket assigned to you.');
    }

    private function guard(string $permission): void
    {
        abort_unless(
            auth('admin')->user()?->can($permission) || auth('admin')->user()?->hasRole('super-admin'),
            403,
        );
    }
}
