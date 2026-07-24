<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CardAuthStatus;
use App\Http\Controllers\Controller;
use App\Models\CardAuthorization;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Card Transactions — "What card authorizations have run?" Read-only list of
 * CardAuthorizations (auth → hold → settle) with status tabs. The JIT-funding
 * pipeline is untouched; this only surfaces it.
 */
class CardTransactionsController extends Controller
{
    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-cards') || $admin->hasRole('super-admin'), 403);

        $status = (string) $request->query('status', 'all');

        $transactions = CardAuthorization::with('card.user', 'fundingAsset')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.card-transactions', [
            'transactions' => $transactions,
            'status' => $status,
            'tabs' => [
                'all' => CardAuthorization::count(),
                CardAuthStatus::Approved->value => CardAuthorization::where('status', CardAuthStatus::Approved->value)->count(),
                CardAuthStatus::Settled->value => CardAuthorization::where('status', CardAuthStatus::Settled->value)->count(),
                CardAuthStatus::Declined->value => CardAuthorization::where('status', CardAuthStatus::Declined->value)->count(),
                CardAuthStatus::Reversed->value => CardAuthorization::where('status', CardAuthStatus::Reversed->value)->count(),
            ],
        ]);
    }
}
