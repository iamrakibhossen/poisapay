<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DepositMethodType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\DepositMethod;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin deposit-method catalogue (DollarHub structure — controller + Blade, not
 * Livewire). The free-form key/value `details` map is submitted as parallel
 * `details[key][]` / `details[value][]` arrays and reassembled here exactly as
 * the old Livewire component did (blank keys skipped). Human amounts are
 * converted to base units against the selected asset's decimals.
 */
class DepositMethodsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManage();

        $tab = $request->query('tab', 'methods') === 'assets' ? 'assets' : 'methods';

        return view('admin.deposit-methods', [
            'tab' => $tab,
            'methods' => DepositMethod::with('asset')
                ->join('assets', 'assets.id', '=', 'deposit_methods.asset_id')
                ->orderBy('assets.symbol')
                ->orderBy('deposit_methods.sort')
                ->orderBy('deposit_methods.name')
                ->select('deposit_methods.*')
                ->get(),
            'assets' => Asset::where('is_active', true)->orderBy('symbol')->get(),
            'types' => DepositMethodType::cases(),
            'stats' => [
                'total' => DepositMethod::count(),
                'active' => DepositMethod::where('is_active', true)->count(),
                'depositable' => Asset::where('is_active', true)->where('deposit_enabled', true)->count(),
            ],
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $request->merge(['is_active' => $request->boolean('is_active')]);

        $data = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'name' => 'required|string|max:120',
            'type' => 'required|in:bank,mobile,crypto,manual',
            'instructions' => 'nullable|string|max:2000',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'fixed_fee' => 'nullable|numeric|min:0',
            'percent_fee_bps' => 'required|integer|min:0|max:10000',
            'is_active' => 'boolean',
            'sort' => 'required|integer|min:0',
        ]);

        try {
            $asset = Asset::findOrFail($data['asset_id']);

            $toBase = fn (?string $human): ?string => ($human ?? '') !== ''
                ? Money::ofDecimal($human, $asset->decimals, $asset->symbol)->baseString()
                : null;

            // Collect the parallel key/value rows into the details map (skip blank keys).
            $keys = (array) $request->input('details.key', []);
            $values = (array) $request->input('details.value', []);
            $details = [];
            foreach ($keys as $i => $key) {
                $key = trim((string) $key);
                if ($key === '') {
                    continue;
                }
                $details[$key] = (string) ($values[$i] ?? '');
            }

            $attributes = [
                'asset_id' => $data['asset_id'],
                'name' => $data['name'],
                'type' => $data['type'],
                'details' => $details,
                'instructions' => ($data['instructions'] ?? null) ?: null,
                'min_amount' => $toBase($request->input('min_amount')) ?? '0',
                'max_amount' => $toBase($request->input('max_amount')),
                'fixed_fee' => $toBase($request->input('fixed_fee')) ?? '0',
                'percent_fee_bps' => (int) $data['percent_fee_bps'],
                'is_active' => (bool) $data['is_active'],
                'sort' => (int) $data['sort'],
            ];

            $id = $request->input('id');
            // Never pass id=null to create() on a HasUuids model (mass-assignment guard).
            $id
                ? tap(DepositMethod::whereKey($id)->firstOrFail())->update($attributes)
                : DepositMethod::create($attributes);

            return redirect()->route('admin.deposit-methods')
                ->with('success', $id ? 'Method updated.' : 'Method created.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.deposit-methods')
                ->with('error', 'Could not save the method: '.$e->getMessage());
        }
    }

    public function toggleActive(Request $request, string $id): RedirectResponse
    {
        $this->authorizeManage();

        try {
            $m = DepositMethod::findOrFail($id);
            $m->update(['is_active' => ! $m->is_active]);

            return redirect()->route('admin.deposit-methods')
                ->with('success', $m->is_active ? 'Method enabled.' : 'Method disabled.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.deposit-methods')
                ->with('error', 'Could not update the method: '.$e->getMessage());
        }
    }

    public function toggleDepositEnabled(Request $request, string $id): RedirectResponse
    {
        $this->authorizeManage();

        try {
            $asset = Asset::findOrFail($id);
            $asset->update(['deposit_enabled' => ! $asset->deposit_enabled]);

            return redirect()->route('admin.deposit-methods', ['tab' => 'assets'])
                ->with('success', $asset->deposit_enabled ? $asset->symbol.' deposits enabled.' : $asset->symbol.' deposits disabled.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.deposit-methods', ['tab' => 'assets'])
                ->with('error', 'Could not update the asset: '.$e->getMessage());
        }
    }

    private function authorizeManage(): void
    {
        abort_unless(auth('admin')->user()?->can('manage-assets') || auth('admin')->user()?->hasRole('super-admin'), 403);
    }
}
