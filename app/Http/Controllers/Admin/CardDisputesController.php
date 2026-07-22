<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Card\ResolveCardDisputeAction;
use App\Http\Controllers\Controller;
use App\Models\CardDispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin card-dispute adjudication (DollarHub structure — controller + Blade,
 * not Livewire). Money-critical: losing a case posts a chargeback that
 * reimburses the cardholder via {@see ResolveCardDisputeAction}.
 */
class CardDisputesController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()->can('manage-card-disputes') || auth('admin')->user()->hasRole('super-admin'), 403);

        $filter = (string) $request->query('filter', 'actionable');

        $disputes = CardDispute::query()
            ->with(['authorization.card.user'])
            ->when($filter === 'actionable', fn ($q) => $q->whereIn('status', ['open', 'represented']))
            ->when(in_array($filter, ['open', 'represented', 'won', 'lost'], true), fn ($q) => $q->where('status', $filter))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.card-disputes', [
            'disputes' => $disputes,
            'filter' => $filter,
            'stats' => [
                'open' => CardDispute::where('status', 'open')->count(),
                'openAmount' => CardDispute::where('status', 'open')->sum('amount'),
                'won' => CardDispute::where('status', 'won')->count(),
                'lost' => CardDispute::where('status', 'lost')->count(),
            ],
        ]);
    }

    public function resolve(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('manage-card-disputes') || auth('admin')->user()->hasRole('super-admin'), 403);

        $data = $request->validate(['outcome' => 'required|in:won,lost']);
        $outcome = $data['outcome'];

        try {
            $dispute = CardDispute::findOrFail($id);

            if (! in_array($dispute->status, ['open', 'represented'], true)) {
                return back()->with('error', 'This dispute has already been resolved.');
            }

            app(ResolveCardDisputeAction::class)->execute($dispute, $outcome);

            return back()->with('success', $outcome === 'lost'
                ? 'Dispute lost — chargeback posted to the cardholder.'
                : 'Dispute marked as won and closed.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not resolve the dispute: '.$e->getMessage());
        }
    }
}
