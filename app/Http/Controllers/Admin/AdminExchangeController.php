<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Enums\LedgerAccountType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Conversion;
use App\Models\LedgerAccount;
use App\Models\TradingPair;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin exchange config (DollarHub structure — controller + Blade, not Livewire).
 * Manages trading pairs / rate (spread) config and reviews swap volume (TDD §5 FX).
 * Viewing needs `view-exchange`; mutations need `manage-exchange`.
 */
class AdminExchangeController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()->can('view-exchange') || auth('admin')->user()->hasRole('super-admin'), 403);

        $pairs = TradingPair::with('fromAsset', 'toAsset')->orderBy('sort')->orderBy('from_asset_id')->get();

        $conversions = Conversion::with('user', 'quote.fromAsset', 'quote.toAsset')
            ->latest()
            ->paginate(15);

        // Spread income per asset (fx:spread_income ledger accounts).
        $spreadAccounts = LedgerAccount::with('asset', 'balance')
            ->where('type', LedgerAccountType::FxSpreadIncome->value)
            ->get();

        $spreadIncome = $spreadAccounts
            ->filter(fn ($a) => ! $a->money()->isZero())
            ->map(fn ($a) => $a->money()->format())
            ->values()
            ->all();

        return view('admin.exchange', [
            'canManage' => $this->canManage(),
            'assets' => Asset::where('is_active', true)->orderBy('sort')->orderBy('symbol')->get(),
            'pairs' => $pairs,
            'conversions' => $conversions,
            'stats' => [
                'total_swaps' => Conversion::count(),
                'today' => Conversion::whereDate('created_at', today())->count(),
                'pairs_traded' => TradingPair::where('is_active', true)->count(),
                'spread_accounts' => $spreadAccounts->count(),
            ],
            'spreadIncome' => $spreadIncome,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $request->merge(['is_active' => $request->boolean('is_active')]);

        $data = $request->validate([
            'id' => 'nullable|exists:trading_pairs,id',
            'fromAssetId' => 'required|integer|exists:assets,id',
            'toAssetId' => 'required|integer|exists:assets,id|different:fromAssetId',
            'spreadBps' => 'nullable|integer|min:0|max:10000',
            'minAmount' => 'required|string',
            'maxAmount' => 'nullable|string',
            'is_active' => 'boolean',
            'sort' => 'integer|min:0',
        ], [
            'toAssetId.different' => 'The from and to assets must be different.',
        ]);

        $editingId = $data['id'] ?? null;

        $from = Asset::find($data['fromAssetId']);
        if (! $from) {
            return back()->withInput()->withErrors(['fromAssetId' => 'Invalid from asset.']);
        }

        $minBase = Money::ofDecimal($data['minAmount'], $from->decimals)->baseString();
        $maxBase = ($data['maxAmount'] ?? '') !== ''
            ? Money::ofDecimal($data['maxAmount'], $from->decimals)->baseString()
            : null;

        $payload = [
            'from_asset_id' => $data['fromAssetId'],
            'to_asset_id' => $data['toAssetId'],
            'spread_bps' => ($data['spreadBps'] ?? '') === '' || ($data['spreadBps'] ?? null) === null ? null : (int) $data['spreadBps'],
            'min_amount' => $minBase,
            'max_amount' => $maxBase,
            'is_active' => $data['is_active'],
            'sort' => $data['sort'] ?? 0,
        ];

        // Enforce the unique (from, to) pair defensively.
        $duplicate = TradingPair::where('from_asset_id', $data['fromAssetId'])
            ->where('to_asset_id', $data['toAssetId'])
            ->when($editingId, fn ($q) => $q->where('id', '!=', $editingId))
            ->exists();

        if ($duplicate) {
            return back()->withInput()->withErrors(['toAssetId' => 'A trading pair for these assets already exists.']);
        }

        try {
            $pair = $editingId
                ? tap(TradingPair::findOrFail($editingId), fn ($p) => $p->update($payload))
                : TradingPair::create($payload);
        } catch (QueryException $e) {
            return back()->withInput()->withErrors(['toAssetId' => 'A trading pair for these assets already exists.']);
        }

        ActivityLogger::log(
            'trading_pair.saved',
            $pair,
            $payload,
            ($editingId ? 'Updated' : 'Created').' trading pair '.$pair->label(),
        );

        return redirect()->route('admin.exchange')->with('success', $editingId ? 'Trading pair updated.' : 'Trading pair added.');
    }

    public function toggleActive(string $id): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $pair = TradingPair::findOrFail($id);
        $pair->update(['is_active' => ! $pair->is_active]);

        ActivityLogger::log(
            'trading_pair.saved',
            $pair,
            ['is_active' => $pair->is_active],
            ($pair->is_active ? 'Enabled' : 'Disabled').' trading pair '.$pair->label(),
        );

        return back()->with('success', $pair->is_active ? 'Pair enabled.' : 'Pair disabled.');
    }

    public function destroy(string $id): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $pair = TradingPair::findOrFail($id);
        $label = $pair->label();
        $pair->delete();

        ActivityLogger::log('trading_pair.deleted', null, ['id' => $id], 'Deleted trading pair '.$label);

        return redirect()->route('admin.exchange')->with('success', 'Trading pair removed.');
    }

    private function canManage(): bool
    {
        return auth('admin')->user()->can('manage-exchange') || auth('admin')->user()->hasRole('super-admin');
    }
}
