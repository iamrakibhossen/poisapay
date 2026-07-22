<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\Chain;
use App\Models\CustodyXpub;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin custody / HD-wallet xpubs CRUD (DollarHub structure — controller + Blade, not
 * Livewire). SECURITY: only extended PUBLIC keys (xpub/tpub/ypub/zpub) are ever accepted
 * or displayed — private keys (xprv/tprv) are rejected by validation AND the DB
 * `ck_never_xpriv` constraint. Spend authority stays in offline custody (§4.2 / D4).
 */
class CustodyController extends Controller
{
    public function index(): View
    {
        $this->authorizeAccess();

        return view('admin.custody', [
            'xpubs' => CustodyXpub::with('chain')
                ->join('chains', 'chains.id', '=', 'custody_xpubs.chain_id')
                ->orderBy('chains.name')
                ->orderBy('custody_xpubs.label')
                ->select('custody_xpubs.*')
                ->get(),
            'chains' => Chain::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $request->merge(['is_active' => $request->boolean('is_active')]);

        $data = $request->validate([
            'id' => 'nullable|exists:custody_xpubs,id',
            'chain_id' => 'required|exists:chains,id',
            'label' => 'required|string|max:80',
            'xpub' => [
                'required',
                'string',
                'max:255',
                'regex:/^(xpub|tpub|ypub|zpub)/',
                'not_regex:/priv|xprv|tprv/i',
            ],
            'derivation_path' => 'required|string|max:64',
            'purpose' => 'required|in:deposit,cold-watch',
            'is_active' => 'boolean',
        ], [
            'xpub.regex' => 'Enter a valid extended PUBLIC key (must start with xpub, tpub, ypub or zpub).',
            'xpub.not_regex' => 'This looks like a private key — only PUBLIC xpubs are allowed.',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);
        $data['xpub'] = trim($data['xpub']);

        try {
            if ($id) {
                $xpub = CustodyXpub::findOrFail($id);
                $xpub->update($data);
                ActivityLogger::log('xpub.updated', $xpub, ['label' => $data['label'], 'chain_id' => $data['chain_id']], 'Custody xpub updated');
            } else {
                $xpub = CustodyXpub::create($data);
                ActivityLogger::log('xpub.registered', $xpub, ['label' => $data['label'], 'chain_id' => $data['chain_id']], 'Custody xpub registered');
            }
        } catch (QueryException $e) {
            // Belt-and-braces: the DB `ck_never_xpriv` constraint is the last line of defence.
            if (str_contains($e->getMessage(), 'ck_never_xpriv')) {
                return back()->withInput()->withErrors(['xpub' => 'This looks like a private key — only PUBLIC xpubs are allowed.']);
            }
            throw $e;
        }

        return redirect()->route('admin.custody')->with('success', $id ? 'Xpub updated.' : 'Xpub registered.');
    }

    public function toggleActive(string $id): RedirectResponse
    {
        $this->authorizeAccess();

        $x = CustodyXpub::findOrFail($id);
        $x->update(['is_active' => ! $x->is_active]);
        ActivityLogger::log('xpub.updated', $x, ['is_active' => $x->is_active], 'Custody xpub toggled');

        return back()->with('success', $x->is_active ? 'Xpub enabled.' : 'Xpub disabled.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeAccess();

        $x = CustodyXpub::findOrFail($id);
        ActivityLogger::log('xpub.deleted', $x, ['label' => $x->label], 'Custody xpub deleted');
        $x->delete();

        return redirect()->route('admin.custody')->with('success', 'Xpub deleted.');
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth('admin')->user()->can('manage-assets') || auth('admin')->user()->hasRole('super-admin'), 403);
    }
}
