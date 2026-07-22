<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Revenue\RequestRevenueWithdrawalAction;
use App\Domain\Revenue\RevenueService;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\RevenueWithdrawal;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin revenue wallet (DollarHub structure — controller + Blade, not Livewire).
 * Company earnings derived from the ledger. Money-critical: requesting a
 * withdrawal records a Pending {@see RevenueWithdrawal} for a second
 * operator to approve — no funds move here. Password-confirmed at the boundary.
 */
class RevenueWalletController extends Controller
{
    public function index(RevenueService $revenue): View
    {
        abort_unless(auth('admin')->user()?->can('view-revenue') || auth('admin')->user()?->hasRole('super-admin'), 403);

        $asset = $this->primaryAsset();

        $balance = $asset ? $revenue->balance($asset) : null;
        $stats = $asset ? $revenue->stats($asset) : null;

        $daily = $asset ? $revenue->dailySeries($asset, 14) : [];
        $monthly = $asset ? $revenue->monthlySeries($asset, 6) : [];

        return view('admin.revenue-wallet', [
            'asset' => $asset,
            'balance' => $balance?->format(),
            'withdrawn' => $asset ? $revenue->withdrawn($asset)->format() : null,
            'available' => $balance?->format(),
            'stats' => $stats,
            'dailyLabels' => array_column($daily, 'label'),
            'dailyValues' => array_column($daily, 'value'),
            'monthlyLabels' => array_column($monthly, 'label'),
            'monthlyValues' => array_column($monthly, 'value'),
            'chains' => Chain::where('is_active', true)->get(),
            'canWithdraw' => auth('admin')->user()?->can('withdraw-revenue') || auth('admin')->user()?->hasRole('super-admin'),
        ]);
    }

    public function withdraw(Request $request, RequestRevenueWithdrawalAction $action, RevenueService $revenue): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('withdraw-revenue') || auth('admin')->user()?->hasRole('super-admin'), 403);

        $data = $request->validate([
            'asset_id' => 'nullable|integer|exists:assets,id',
            'amount' => 'required|numeric|gt:0',
            'network' => 'nullable|string|max:60',
            'destination' => 'required|string|max:160',
            'note' => 'nullable|string|max:500',
            'password' => 'required|string',
        ]);

        // The merged Revenue page sends asset_id; fall back to the primary asset.
        $asset = ! empty($data['asset_id']) ? Asset::find($data['asset_id']) : $this->primaryAsset();
        abort_unless($asset !== null, 404);

        // The approval flow is an on-chain broadcast — fiat has no chain, so fiat
        // revenue is taken via the instant "Record payout" instead.
        if ($asset->isFiat()) {
            return back()->withInput()->withErrors(['amount' => 'Fiat revenue has no on-chain payout — use the instant Record payout instead.']);
        }

        $available = $revenue->balance($asset);
        $network = ($data['network'] ?? null) ?: ($asset->chain?->name ?? $asset->symbol);

        if (! Hash::check($data['password'], auth('admin')->user()->password)) {
            return back()->withInput()->withErrors(['password' => 'The password is incorrect.']);
        }

        try {
            $money = Money::ofDecimal($data['amount'], $asset->decimals, $asset->symbol);

            if ($money->isGreaterThanOrEqual($available) && ! $money->equals($available)) {
                return back()->withInput()->withErrors(['amount' => "You can withdraw at most {$available->format()}."]);
            }

            $action->execute(
                auth('admin')->user(),
                $asset,
                $money,
                $network,
                $data['destination'],
                $data['note'] ?? null ?: null,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Revenue withdrawal requested — awaiting approval.');
    }

    protected function primaryAsset(): ?Asset
    {
        return Asset::where('symbol', 'USDT')->first() ?? Asset::where('kind', 'crypto')->first();
    }
}
