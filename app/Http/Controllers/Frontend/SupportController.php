<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Support\SupportTicketService;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * User support centre (Wave 6): open tickets, browse them, and reply. Traditional
 * controller + Blade (form POST → redirect + flash).
 */
class SupportController extends Controller
{
    private const CATEGORIES = ['general', 'account', 'deposit', 'withdrawal', 'card', 'kyc', 'other'];

    public function index(Request $request): View
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->withCount('messages')
            ->latest('updated_at')
            ->paginate(20);

        return view('frontend.support.index', ['tickets' => $tickets]);
    }

    public function create(): View
    {
        return view('frontend.support.create', ['categories' => self::CATEGORIES]);
    }

    public function store(Request $request, SupportTicketService $service): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', 'in:'.implode(',', self::CATEGORIES)],
            'priority' => ['required', 'in:low,normal,high'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = $service->open($request->user(), $data['subject'], $data['category'], $data['priority'], $data['body']);

        return redirect()->route('support.show', $ticket->id)->with('status', 'Ticket created. Our team will get back to you.');
    }

    public function show(Request $request, string $id): View
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at')])
            ->findOrFail($id);

        return view('frontend.support.show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, string $id, SupportTicketService $service): RedirectResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);

        if ($ticket->status === SupportTicketStatus::Closed) {
            return back()->withErrors(['body' => 'This ticket is closed. Open a new one if you still need help.']);
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $service->userReply($ticket, $request->user(), $data['body']);

        return back()->with('status', 'Reply sent.');
    }
}
