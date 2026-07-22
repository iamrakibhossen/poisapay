<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\Chain;
use App\Models\RpcEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin RPC endpoints CRUD (DollarHub structure — controller + Blade, not Livewire).
 * JSON-RPC nodes the indexer/broadcaster failover across, ordered by priority per chain.
 */
class RpcEndpointsController extends Controller
{
    public function index(): View
    {
        $this->authorizeAccess();

        return view('admin.rpc-endpoints', [
            'endpoints' => RpcEndpoint::with('chain')
                ->join('chains', 'chains.id', '=', 'rpc_endpoints.chain_id')
                ->orderBy('chains.name')
                ->orderBy('rpc_endpoints.priority')
                ->select('rpc_endpoints.*')
                ->get(),
            'chains' => Chain::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $request->merge(['is_active' => $request->boolean('is_active')]);

        $data = $request->validate([
            'id' => 'nullable|exists:rpc_endpoints,id',
            'chain_id' => 'required|exists:chains,id',
            'name' => 'required|string|max:80',
            'url' => 'required|url|max:255',
            'priority' => 'required|integer|min:1|max:99',
            'weight' => 'required|integer|min:1|max:100',
            'is_active' => 'boolean',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        if ($id) {
            $endpoint = RpcEndpoint::findOrFail($id);
            $endpoint->update($data);
        } else {
            $endpoint = RpcEndpoint::create($data);
        }

        ActivityLogger::log('rpc.saved', $endpoint, $data, $id ? 'RPC endpoint updated' : 'RPC endpoint created');

        return redirect()->route('admin.rpc-endpoints')->with('success', $id ? 'Endpoint updated.' : 'Endpoint added.');
    }

    public function toggleActive(string $id): RedirectResponse
    {
        $this->authorizeAccess();

        $e = RpcEndpoint::findOrFail($id);
        $e->update(['is_active' => ! $e->is_active]);
        ActivityLogger::log('rpc.saved', $e, ['is_active' => $e->is_active], 'RPC endpoint toggled');

        return back()->with('success', $e->is_active ? 'Endpoint enabled.' : 'Endpoint disabled.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeAccess();

        $e = RpcEndpoint::findOrFail($id);
        ActivityLogger::log('rpc.deleted', $e, ['name' => $e->name, 'url' => $e->url], 'RPC endpoint deleted');
        $e->delete();

        return redirect()->route('admin.rpc-endpoints')->with('success', 'Endpoint deleted.');
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth('admin')->user()->can('manage-assets') || auth('admin')->user()->hasRole('super-admin'), 403);
    }
}
