<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\ColdRefillRequest;
use App\Models\TreasuryMove;
use Illuminate\View\View;

/**
 * Treasury Transfers — "What hot↔cold rebalancing has happened?" Read-only view
 * of on-chain treasury moves and cold→hot refill requests. Purely informational;
 * the rebalancing pipeline is untouched.
 */
class TreasuryTransfersController extends Controller
{
    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-treasury') || $admin->hasRole('super-admin'), 403);

        return view('admin.treasury-transfers', [
            'moves' => TreasuryMove::latest()->limit(30)->get(),
            'refills' => ColdRefillRequest::latest()->limit(30)->get(),
            'assetSymbols' => Asset::pluck('symbol', 'id'),
            'chainNames' => Chain::pluck('name', 'id'),
        ]);
    }
}
