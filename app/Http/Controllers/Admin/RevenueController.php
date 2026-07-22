<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Ledger\WithdrawProfitAction;
use App\Domain\Revenue\RevenueService;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\ProfitPayout;
use App\Models\RevenueWithdrawal;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Unified admin Revenue page (DollarHub structure — controller + Blade). Merges
 * what used to be four screens: the earnings dashboard (balance/stats/charts),
 * profit-by-coin with an instant on-chain payout, and tabs for payout history,
 * fee transactions and pending approvals. Money-critical: the instant payout
 * moves the crypto out of the treasury and records an auditable {@see ProfitPayout};
 * never touches user funds.
 */
class RevenueController extends Controller
{
    public function index(WithdrawProfitAction $profit, RevenueService $revenue): View
    {
        abort_unless(auth('admin')->user()?->can('view-revenue') || auth('admin')->user()?->hasRole('super-admin'), 403);

        // Profit grouped by coin, with a per-network breakdown (fees are per chain).
        $coins = Asset::with(['chain', 'currency'])->where('is_active', true)->get()
            ->groupBy(fn (Asset $a) => $a->currency_id ?? $a->symbol)
            ->map(function ($group) use ($profit, $revenue) {
                $lead = $group->sortBy('id')->first();
                $available = null;
                $withdrawn = null;
                $networks = [];

                foreach ($group->sortBy('id') as $asset) {
                    $avail = $profit->availableProfit($asset);
                    $wd = $revenue->withdrawn($asset);
                    $available = $available ? $available->plus($avail) : $avail;
                    $withdrawn = $withdrawn ? $withdrawn->plus($wd) : $wd;

                    $networks[] = [
                        'id' => $asset->id,
                        'network' => $asset->chain?->name ?? ($asset->isFiat() ? 'Fiat' : '—'),
                        'available' => $avail->format(),
                        'availablePositive' => $avail->isPositive(),
                        'availableDecimal' => $avail->toDecimal(),
                    ];
                }

                return [
                    'symbol' => $lead->symbol,
                    'name' => $lead->name,
                    'isFiat' => $lead->isFiat(),
                    'available' => $available->format(),
                    'withdrawn' => $withdrawn->format(),
                    'hasProfit' => $available->isPositive() || $withdrawn->isPositive(),
                    'networks' => $networks,
                ];
            })
            ->filter(fn ($c) => $c['hasProfit'])
            ->sortByDesc('available')
            ->values();

        // Dashboard stats + charts for the primary asset (USDT).
        $primary = Asset::where('symbol', 'USDT')->orderBy('id')->first() ?? Asset::where('kind', 'crypto')->first();
        $stats = $primary ? $revenue->stats($primary) : null;
        $daily = $primary ? $revenue->dailySeries($primary, 14) : [];
        $monthly = $primary ? $revenue->monthlySeries($primary, 6) : [];

        return view('admin.revenue', [
            'coins' => $coins,
            'primarySymbol' => $primary?->symbol,
            'stats' => $stats,
            'dailyLabels' => array_column($daily, 'label'),
            'dailyValues' => array_column($daily, 'value'),
            'monthlyLabels' => array_column($monthly, 'label'),
            'monthlyValues' => array_column($monthly, 'value'),
            'payouts' => ProfitPayout::with(['asset', 'operator'])->latest()->limit(15)->get(),
            'transactions' => $revenue->transactionsQuery()->limit(25)->get(),
            'feeTypeLabel' => fn ($accountType, $entryType) => $revenue->feeTypeLabel($accountType, $entryType),
            'approvals' => RevenueWithdrawal::with(['asset', 'creator', 'approver'])->latest()->limit(15)->get(),
            'pendingCount' => RevenueWithdrawal::where('status', 'pending')->count(),
            'assets' => Asset::where('is_active', true)->orderBy('sort')->orderBy('symbol')->get(),
            'canWithdraw' => auth('admin')->user()?->can('withdraw-profit') || auth('admin')->user()?->hasRole('super-admin'),
            'canRequest' => auth('admin')->user()?->can('withdraw-revenue') || auth('admin')->user()?->hasRole('super-admin'),
            'canApprove' => auth('admin')->user()?->can('approve-revenue-withdrawal') || auth('admin')->user()?->hasRole('super-admin'),
        ]);
    }

    public function withdraw(Request $request, WithdrawProfitAction $action): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('withdraw-profit') || auth('admin')->user()?->hasRole('super-admin'), 403);

        $data = $request->validate([
            'asset_id' => 'required|integer',
            'amount' => 'required|numeric|gt:0',
            'destination' => 'nullable|string|max:160',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $asset = Asset::findOrFail($data['asset_id']);
            $money = Money::ofDecimal($data['amount'], $asset->decimals, $asset->symbol);

            $action->execute(
                auth('admin')->user(),
                $asset,
                $money,
                $data['destination'] ?? null ?: null,
                $data['note'] ?? null ?: null,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Profit withdrawal recorded and broadcast.');
    }
}
