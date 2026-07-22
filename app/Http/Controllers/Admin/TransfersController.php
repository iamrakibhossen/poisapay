<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TransferKind;
use App\Http\Controllers\Controller;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin transfers list (DollarHub structure — controller + Blade, not Livewire).
 * Read-only operational view; the kind filter + pagination are query-string driven.
 */
class TransfersController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()->isOperator(), 403);

        $kind = (string) $request->query('kind', 'all');

        $transfers = Transfer::with('sender', 'recipient', 'asset')
            ->when($kind !== 'all', fn ($q) => $q->where('kind', $kind))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.transfers', [
            'transfers' => $transfers,
            'kind' => $kind,
            'tabs' => [
                'all' => Transfer::count(),
                'internal' => Transfer::where('kind', TransferKind::Internal->value)->count(),
                'payout' => Transfer::where('kind', TransferKind::Payout->value)->count(),
                'remittance' => Transfer::where('kind', TransferKind::Remittance->value)->count(),
            ],
        ]);
    }
}
