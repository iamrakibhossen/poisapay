<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Analytics\FlowAnalytics;
use App\Domain\Transaction\TransactionFeedService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Transactions page — server-rendered. The controller builds the (filtered,
 * paginated) activity feed and passes it straight to the Blade view; filters and
 * pagination are plain query-string links. No JSON API.
 */
class TransactionController extends Controller
{
    public function index(Request $request, TransactionFeedService $feed, FlowAnalytics $analytics): View
    {
        $filters = [
            'type' => (string) $request->query('type', 'all'),
            'asset' => (string) $request->query('asset', 'all'),
            'search' => (string) $request->query('search', ''),
        ];

        $data = $feed->feed($request->user(), $filters, (int) $request->query('page', 1), 20);

        return view('frontend.transactions', [
            'feed' => $data,
            'filters' => $filters,
            'analytics' => $analytics->forUser($request->user()),
        ]);
    }

    /** Unified detail page for a single transaction, resolved by source + id. */
    public function show(Request $request, string $source, string $id, TransactionFeedService $feed): View
    {
        $detail = $feed->detail($request->user(), $source, $id);
        abort_if($detail === null, 404);

        return view('frontend.transaction-show', ['tx' => $detail]);
    }
}
