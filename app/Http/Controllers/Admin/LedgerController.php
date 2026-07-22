<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Ledger\ReverseEntryAction;
use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin ledger view (DollarHub structure — controller + Blade, not Livewire).
 * Double-entry journal. Reversing an entry posts a new balanced reversing entry
 * via {@see ReverseEntryAction} (§5.3) — corrections are never mutations.
 */
class LedgerController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()->can('view-ledger') || auth('admin')->user()->hasRole('super-admin'), 403);

        $search = (string) $request->query('search', '');
        $expandedId = $request->query('entry');

        $entries = JournalEntry::withCount('lines')
            ->when($search !== '', fn ($q) => $q
                ->where('type', 'like', '%'.$search.'%')
                ->orWhere('idempotency_key', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $lines = $expandedId
            ? JournalEntry::with(['lines.account', 'lines.asset'])->find($expandedId)?->lines
            : null;

        return view('admin.ledger', [
            'entries' => $entries,
            'lines' => $lines,
            'expandedId' => $expandedId,
            'search' => $search,
            'canReverse' => auth('admin')->user()->can('manage-treasury') || auth('admin')->user()->hasRole('super-admin'),
        ]);
    }

    public function reverse(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('manage-treasury') || auth('admin')->user()->hasRole('super-admin'), 403);

        $reason = trim((string) $request->input('reason', ''));

        $entry = JournalEntry::findOrFail($id);

        try {
            app(ReverseEntryAction::class)->execute($entry, $reason !== '' ? $reason : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Entry reversed — a balanced reversing entry was posted.');
    }
}
