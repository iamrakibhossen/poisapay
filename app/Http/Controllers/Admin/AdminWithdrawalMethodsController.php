<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\WithdrawalMethod;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin withdrawal-method (fiat payout rail) catalogue (DollarHub structure —
 * controller + Blade, not Livewire). Rails are bank|mobile against fiat assets;
 * the single `number_label` is stored inside the `details` JSON. Human amounts
 * are converted to base units against the selected asset's decimals.
 */
class AdminWithdrawalMethodsController extends Controller
{
    public function index(): View
    {
        $this->authorizeManage();

        return view('admin.withdrawal-methods', [
            'methods' => WithdrawalMethod::with('asset')
                ->join('assets', 'assets.id', '=', 'withdrawal_methods.asset_id')
                ->orderBy('assets.symbol')
                ->orderBy('withdrawal_methods.sort')
                ->orderBy('withdrawal_methods.name')
                ->select('withdrawal_methods.*')
                ->get(),
            'assets' => Asset::where('kind', 'fiat')->where('is_active', true)->orderBy('symbol')->get(),
            'types' => [
                'bank' => 'Bank transfer',
                'mobile' => 'Mobile wallet',
            ],
            'stats' => [
                'total' => WithdrawalMethod::count(),
                'active' => WithdrawalMethod::where('is_active', true)->count(),
                'currencies' => Asset::where('kind', 'fiat')->where('is_active', true)->count(),
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
            'type' => 'required|in:bank,mobile',
            'number_label' => 'nullable|string|max:120',
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

            $numberLabel = trim((string) ($data['number_label'] ?? ''));
            $details = $numberLabel !== '' ? ['number_label' => $numberLabel] : [];

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
                ? tap(WithdrawalMethod::whereKey($id)->firstOrFail())->update($attributes)
                : WithdrawalMethod::create($attributes);

            return redirect()->route('admin.withdrawal-methods')
                ->with('success', $id ? 'Method updated.' : 'Method created.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.withdrawal-methods')
                ->with('error', 'Could not save the method: '.$e->getMessage());
        }
    }

    public function toggleActive(Request $request, string $id): RedirectResponse
    {
        $this->authorizeManage();

        try {
            $m = WithdrawalMethod::findOrFail($id);
            $m->update(['is_active' => ! $m->is_active]);

            return redirect()->route('admin.withdrawal-methods')
                ->with('success', $m->is_active ? 'Method enabled.' : 'Method disabled.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.withdrawal-methods')
                ->with('error', 'Could not update the method: '.$e->getMessage());
        }
    }

    private function authorizeManage(): void
    {
        abort_unless(auth('admin')->user()?->can('manage-assets') || auth('admin')->user()?->hasRole('super-admin'), 403);
    }
}
