<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Compliance\ComplianceListService;
use App\Http\Controllers\Controller;
use App\Models\ComplianceListEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Operator management of persistent sanctions / watch / whitelist entries (Wave 5).
 */
class ComplianceListController extends Controller
{
    public function index(Request $request): View
    {
        $this->guard();

        return view('admin.compliance-lists', [
            'entries' => ComplianceListEntry::latest()->paginate(50),
        ]);
    }

    public function store(Request $request, ComplianceListService $lists): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'list' => ['required', 'in:denylist,watchlist,whitelist'],
            'kind' => ['required', 'in:name,address,country,user,email'],
            'value' => ['required', 'string', 'max:191'],
            'reason' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:64'],
        ]);

        $lists->add($data['list'], $data['kind'], $data['value'], $data['reason'] ?? null, $data['source'] ?? 'manual', auth('admin')->user());

        return back()->with('status', 'List entry added.');
    }

    public function destroy(Request $request, string $id, ComplianceListService $lists): RedirectResponse
    {
        $this->guard();
        $lists->remove($id);

        return back()->with('status', 'List entry removed.');
    }

    private function guard(): void
    {
        abort_unless(
            auth('admin')->user()?->can('view-compliance') || auth('admin')->user()?->hasRole('super-admin'),
            403,
        );
    }
}
