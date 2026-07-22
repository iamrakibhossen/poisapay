<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Credit\CreditService;
use App\Domain\Credit\DrawCreditAction;
use App\Domain\Credit\OpenCreditLineAction;
use App\Domain\Credit\RepayCreditAction;
use App\Domain\Wallet\WalletService;
use App\Enums\CreditStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\CreditLine;
use App\Models\CreditTransaction;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Crypto-backed credit — server-rendered. {@see index()} renders the active line
 * (LTV/debt/available + draw/repay forms) or the open-line form. {@see openLine()},
 * {@see draw()} and {@see repay()} wrap the ledger-backed credit actions and redirect
 * back with a flash message (or validation errors). Money-critical — all guards
 * (LTV caps, funds) live in the domain actions.
 */
class CreditController extends Controller
{
    public function index(Request $request, WalletService $wallets, CreditService $credit): View
    {
        $line = $this->activeLine($request);

        $data = [
            'fundedCrypto' => $this->fundedCrypto($wallets, $request->user())->map(fn ($w) => [
                'assetId' => $w->asset->id,
                'symbol' => $w->asset->symbol,
                'available' => $w->available->format(),
            ])->values(),
            'principalAssets' => Asset::where('is_active', true)->where('kind', 'crypto')
                ->orderBy('sort')->orderBy('symbol')->get()
                ->map(fn (Asset $a) => ['id' => $a->id, 'symbol' => $a->symbol, 'name' => $a->name])->values(),
            'line' => null,
        ];

        if ($line) {
            $ltvBps = $credit->currentLtvBps($line);
            $maxLtv = $line->max_ltv_bps ?: 6000;
            $liqLtv = $line->liquidation_ltv_bps ?: 8000;
            $pct = min(100, $maxLtv > 0 ? (int) round($ltvBps / max($liqLtv, 1) * 100) : 0);
            $barColor = $ltvBps >= $liqLtv ? 'bg-rose-500' : ($ltvBps >= $maxLtv ? 'bg-amber-500' : 'bg-emerald-500');

            $availableToDraw = Money::ofBase($credit->availableToDrawBase($line), $line->principalAsset->decimals, $line->principalAsset->symbol);
            $debt = Money::ofBase($credit->debtBase($line), $line->principalAsset->decimals, $line->principalAsset->symbol);

            $data['line'] = [
                'id' => $line->id,
                'collateralSymbol' => $line->collateralAsset->symbol,
                'principalSymbol' => $line->principalAsset->symbol,
                'status' => ['label' => $line->status->label(), 'color' => $line->status->color()],
                'collateral' => $line->collateralAsset->money($line->collateral_amount)->format(),
                'principal' => $line->principalAsset->money($line->principal_drawn)->format(),
                'fee' => $line->principalAsset->money($line->accrued_fee)->format(),
                'availableToDraw' => $availableToDraw->format(),
                'debt' => $debt->format(),
                'ltvPercent' => number_format($ltvBps / 100, 2),
                'maxLtvPercent' => number_format($maxLtv / 100, 0),
                'liqLtvPercent' => number_format($liqLtv / 100, 0),
                'barPct' => $pct,
                'barColor' => $barColor,
                'transactions' => CreditTransaction::with('asset')
                    ->where('credit_line_id', $line->id)->latest()->limit(20)->get()
                    ->map(fn (CreditTransaction $tx) => [
                        'type' => $tx->type,
                        'symbol' => $tx->asset->symbol,
                        'amount' => $tx->money()->format(),
                        'at_human' => $tx->created_at->diffForHumans(),
                    ])->values(),
            ];
        }

        return view('frontend.credit', $data);
    }

    public function openLine(Request $request, OpenCreditLineAction $open): RedirectResponse
    {
        $validated = $request->validate([
            'collateralAssetId' => ['required', 'integer'],
            'principalAssetId' => ['required', 'integer'],
            'collateralAmount' => ['required', 'string'],
        ]);

        $collateral = Asset::where('is_active', true)->where('kind', 'crypto')->find($validated['collateralAssetId']);
        $principal = Asset::where('is_active', true)->where('kind', 'crypto')->find($validated['principalAssetId']);

        if (! $collateral || ! $principal) {
            throw ValidationException::withMessages(['collateralAssetId' => 'Please choose valid assets.']);
        }

        try {
            $amount = Money::ofDecimal($validated['collateralAmount'], $collateral->decimals, $collateral->symbol);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['collateralAmount' => 'Enter a valid amount.']);
        }

        try {
            $open->execute($request->user(), $collateral, $amount, $principal);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['collateralAmount' => $e->getMessage()]);
        }

        return redirect()->route('credit')->with('success', 'Credit line opened with '.$amount->format().' collateral.');
    }

    public function draw(Request $request, DrawCreditAction $drawAction): RedirectResponse
    {
        $line = $this->activeLine($request);
        if (! $line) {
            throw ValidationException::withMessages(['drawAmount' => 'No active credit line.']);
        }

        $amount = $this->parsePositive($request->input('drawAmount'), $line, 'drawAmount');

        try {
            $drawAction->execute($line, $amount);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['drawAmount' => $e->getMessage()]);
        }

        return redirect()->route('credit')->with('success', 'Drew '.$amount->format().'.');
    }

    public function repay(Request $request, RepayCreditAction $repayAction): RedirectResponse
    {
        $line = $this->activeLine($request);
        if (! $line) {
            throw ValidationException::withMessages(['repayAmount' => 'No active credit line.']);
        }

        $amount = $this->parsePositive($request->input('repayAmount'), $line, 'repayAmount');

        try {
            $repayAction->execute($line, $amount);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['repayAmount' => $e->getMessage()]);
        }

        return redirect()->route('credit')->with('success', 'Repaid '.$amount->format().'.');
    }

    private function parsePositive($value, CreditLine $line, string $field): Money
    {
        try {
            $amount = Money::ofDecimal((string) $value, $line->principalAsset->decimals, $line->principalAsset->symbol);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Enter a valid amount.']);
        }

        if (! $amount->isPositive()) {
            throw ValidationException::withMessages([$field => 'Amount must be greater than zero.']);
        }

        return $amount;
    }

    private function activeLine(Request $request): ?CreditLine
    {
        return CreditLine::with('collateralAsset', 'principalAsset')
            ->where('user_id', $request->user()->id)
            ->where('status', CreditStatus::Active->value)
            ->latest()->first();
    }

    private function fundedCrypto(WalletService $wallets, $user)
    {
        return $wallets->fundedWallets($user)
            ->filter(fn ($w) => $w->asset->kind->value === 'crypto')
            ->values();
    }
}
