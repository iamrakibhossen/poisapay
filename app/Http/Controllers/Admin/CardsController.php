<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Card\CardService;
use App\Domain\Card\RefundCardAuthAction;
use App\Enums\CardAuthStatus;
use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin issued-cards console (DollarHub structure — controller + Blade, not
 * Livewire). Never exposes PAN/CVV — only masked last4. Freeze blocks spending;
 * refund posts money back to the cardholder via {@see RefundCardAuthAction}.
 */
class CardsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()->can('view-cards') || auth('admin')->user()->hasRole('super-admin'), 403);

        $search = (string) $request->query('search', '');
        $status = (string) $request->query('status', 'all');
        $type = (string) $request->query('type', 'all');

        $cards = Card::query()
            ->with(['user', 'provider'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('last4', 'like', '%'.$search.'%')
                ->orWhereHas('user', fn ($u) => $u
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'))))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $monthStart = Carbon::now()->startOfMonth();

        // Recent authorisations per listed card, for the Alpine detail modal.
        $authorizations = CardAuthorization::whereIn('card_id', $cards->pluck('id'))
            ->latest()
            ->get()
            ->groupBy('card_id');

        return view('admin.cards', [
            'cards' => $cards,
            'authorizations' => $authorizations,
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'stats' => [
                'total' => Card::count(),
                'active' => Card::where('status', CardStatus::Active->value)->count(),
                'frozen' => Card::where('status', CardStatus::Frozen->value)->count(),
                'spend' => CardAuthorization::where('status', CardAuthStatus::Settled->value)
                    ->where('created_at', '>=', $monthStart)
                    ->sum('amount'),
            ],
        ]);
    }

    public function toggleFreeze(string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('manage-cards') || auth('admin')->user()->hasRole('super-admin'), 403);

        try {
            $card = Card::findOrFail($id);

            if ($card->status === CardStatus::Closed) {
                return back()->with('error', 'A closed card cannot change status.');
            }

            // Routed through the provider so a real issuer is told to (un)freeze;
            // frozen_by stays null — the actor is an operator on the `admin` guard,
            // not a `users` row (the status flip is what gates spending).
            if ($card->status === CardStatus::Frozen) {
                app(CardService::class)->unfreeze($card);

                return back()->with('success', 'Card unfrozen.');
            }

            app(CardService::class)->freeze($card);

            return back()->with('success', 'Card frozen.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not update the card: '.$e->getMessage());
        }
    }

    public function refund(string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('manage-cards') || auth('admin')->user()->hasRole('super-admin'), 403);

        try {
            $auth = CardAuthorization::with('card')->findOrFail($id);
            app(RefundCardAuthAction::class)->execute($auth, null, 'full');

            return back()->with('success', 'Full refund posted to the cardholder.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Refund failed: '.$e->getMessage());
        }
    }
}
