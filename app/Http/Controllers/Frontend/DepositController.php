<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Custody\AllocateDepositAddressAction;
use App\Domain\Deposit\SubmitManualDepositAction;
use App\Domain\Exchange\Contracts\RateProvider;
use App\Enums\DepositMethodType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Deposit;
use App\Models\DepositAddress;
use App\Models\DepositMethod;
use App\Support\BaseCurrency;
use App\Support\Money;
use App\Support\Qr;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Deposit — traditional server-rendered MVC. Currency-first → method →
 * address/amount flow, with the step held in the query string (?asset, ?method).
 * {@see index()} renders the depositable currencies, the chosen method, and any
 * allocated on-chain address + QR (server-side); {@see submit()} records a manual
 * deposit for review. Crypto deposits arrive on-chain and require no submission.
 */
class DepositController extends Controller
{
    public function index(Request $request): View
    {
        $assets = Asset::depositable()->with(['chain', 'depositMethods'])
            ->orderBy('sort')->orderBy('symbol')->get()
            // Only surface currencies with a real deposit rail: an on-chain network
            // (crypto) or at least one active deposit method (fiat).
            ->filter(fn (Asset $a) => $a->depositMethods->isNotEmpty() || (! $a->isFiat() && $a->chain))
            ->values();

        $selectedAsset = null;
        $selectedMethod = null;
        $address = null;
        $addressQr = null;
        $addressNetwork = null;
        $addressError = null;

        $assetId = $request->query('asset');
        if ($assetId) {
            $selectedAsset = $assets->firstWhere('id', (int) $assetId);
        }

        if ($selectedAsset) {
            $methodId = $request->query('method');

            if ($methodId) {
                $selectedMethod = $selectedAsset->depositMethods->firstWhere('id', $methodId);
                if ($selectedMethod && $selectedMethod->type === DepositMethodType::Crypto) {
                    $address = $selectedMethod->details['address'] ?? null;
                    $addressNetwork = $selectedMethod->details['network'] ?? $selectedAsset->name;
                    $addressQr = $address ? Qr::svg($address) : null;
                }
            } elseif ($selectedAsset->depositMethods->isEmpty()) {
                // No methods but a chain → allocate/show the on-chain network address (idempotent).
                $addressNetwork = $selectedAsset->chain?->name ?? $selectedAsset->name;
                [$address, $addressError] = $this->allocateNetworkAddress($request->user(), $selectedAsset);
                $addressQr = $address ? Qr::svg($address) : null;
            }
        }

        return view('frontend.deposit', [
            'depositEnabled' => (bool) feature('deposit_enabled'),
            'custodySimulated' => (bool) config('poisapay.custody_simulated'),
            'assets' => $assets,
            'selectedAsset' => $selectedAsset,
            'selectedMethod' => $selectedMethod,
            'address' => $address,
            'addressQr' => $addressQr,
            'addressNetwork' => $addressNetwork,
            'addressError' => $addressError,
            'recentCount' => Deposit::where('user_id', $request->user()->id)->count(),
        ]);
    }

    /** Dedicated deposit history page — the full, paginated list of the user's deposits. */
    public function history(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'status' => (string) $request->query('status', 'all'),
            'asset' => (string) $request->query('asset', 'all'),
            'search' => trim((string) $request->query('search', '')),
        ];

        // ── Account-wide stats (independent of the active filters) ──
        $base = Deposit::where('user_id', $user->id);
        $stats = [
            'total' => (clone $base)->count(),
            'month' => (clone $base)->where('created_at', '>=', now()->startOfMonth())->count(),
            'pending' => (clone $base)->whereIn('status', ['detected', 'confirming'])->count(),
            'received' => $this->receivedInBaseCurrency($user),
        ];

        // Assets this user has ever deposited — powers the asset filter.
        $symbols = Asset::whereIn('id', (clone $base)->select('asset_id'))
            ->orderBy('symbol')->pluck('symbol');

        // ── Filtered, paginated feed ──
        $query = Deposit::with(['asset.chain', 'depositMethod', 'onchainTx'])
            ->where('user_id', $user->id);

        $statusGroups = ['pending' => ['detected', 'confirming'], 'credited' => ['credited'], 'failed' => ['orphaned']];
        if (isset($statusGroups[$filters['status']])) {
            $query->whereIn('status', $statusGroups[$filters['status']]);
        }

        if ($filters['asset'] !== 'all') {
            $query->whereHas('asset', fn ($q) => $q->where('symbol', $filters['asset']));
        }

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q
                ->where('reference', 'ilike', $term)
                ->orWhereHas('onchainTx', fn ($t) => $t->where('tx_hash', 'ilike', $term)));
        }

        $deposits = $query
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (Deposit $d) {
                $tx = $d->onchainTx;
                $hash = $tx?->tx_hash;

                return [
                    'id' => $d->id,
                    'symbol' => $d->asset->symbol,
                    'name' => $d->asset->name,
                    'network' => $d->asset->chain?->name ?? ($d->asset->isFiat() ? 'Fiat' : $d->asset->name),
                    'amount' => $d->money()->format(),
                    'fee' => $d->fee > 0 ? $d->feeMoney()->format() : null,
                    'net' => $d->netMoney()->format(),
                    'source' => $d->source === 'manual' ? ($d->depositMethod?->name ?? 'Manual') : 'On-chain',
                    'sourceKind' => $d->source === 'manual' ? 'Manual' : 'On-chain',
                    'reference' => $d->reference,
                    'status' => $d->status->label(),
                    'statusColor' => $d->status->color(),
                    'at' => $d->created_at->toIso8601String(),
                    'creditedAt' => $d->credited_at?->toIso8601String(),
                    // On-chain details.
                    'txid' => $hash,
                    'txidShort' => $hash ? Str::substr($hash, 0, 10).'…'.Str::substr($hash, -8) : null,
                    'explorer' => $d->asset->chain?->explorerTxUrl($hash),
                    'from' => $tx?->from_address,
                    'confirmations' => $tx?->confirmations,
                    'requiredConfirmations' => $d->required_confirmations,
                ];
            });

        return view('frontend.deposits', [
            'deposits' => $deposits,
            'stats' => $stats,
            'filters' => $filters,
            'symbols' => $symbols,
        ]);
    }

    /**
     * All-time credited deposits, valued in the user's base currency.
     * Mirrors {@see \App\Domain\Analytics\FlowAnalytics} so the figure never drifts.
     */
    private function receivedInBaseCurrency($user): string
    {
        $base = BaseCurrency::assetFor($user);
        if (! $base) {
            return '—';
        }

        $rates = app(RateProvider::class);
        $total = BigDecimal::zero();

        foreach (Deposit::with('asset')->where('user_id', $user->id)->where('status', 'credited')->get() as $d) {
            $total = $total->plus(
                BigDecimal::ofUnscaledValue($d->amount, $d->asset->decimals)->multipliedBy($rates->rate($d->asset, $base))
            );
        }

        return Money::ofBase(
            $total->withPointMovedRight($base->decimals)->toScale(0, RoundingMode::DOWN)->toBigInteger(),
            $base->decimals,
            $base->symbol,
        )->format(2);
    }

    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assetId' => ['required', 'integer'],
            'methodId' => ['required', 'string'],
            'amount' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $asset = Asset::with('depositMethods')->find($validated['assetId']);
        $method = $asset?->depositMethods->firstWhere('id', $validated['methodId']);

        if (! $asset || ! $method) {
            throw ValidationException::withMessages(['methodId' => 'Select a currency and deposit method first.']);
        }
        if ($method->type === DepositMethodType::Crypto) {
            throw ValidationException::withMessages(['methodId' => 'Crypto deposits arrive on-chain — no submission is required.']);
        }
        if (trim($validated['amount']) === '') {
            throw ValidationException::withMessages(['amount' => 'Please enter an amount.']);
        }

        try {
            $money = Money::ofDecimal($validated['amount'], $asset->decimals, $asset->symbol);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid amount.']);
        }

        try {
            app(SubmitManualDepositAction::class)->execute($request->user(), $method, $money, $validated['reference'] ?? '');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('deposit.index', ['asset' => $asset->id, 'method' => $method->id])
            ->with('success', 'Deposit submitted for review.');
    }

    /**
     * @return array{0: ?string, 1: ?string} [address, error]
     */
    private function allocateNetworkAddress($user, Asset $asset): array
    {
        $chain = $asset->chain;
        if (! $chain || ! $chain->is_active) {
            return [null, 'This currency is not available for on-chain deposits.'];
        }

        try {
            $result = app(AllocateDepositAddressAction::class)->execute($user, $chain);

            return [$result->address, null];
        } catch (\Throwable) {
            // Custody may not be provisioned in demo — fall back to any existing address.
            $existing = DepositAddress::where('user_id', $user->id)->where('chain_id', $chain->id)->first();

            return [
                $existing?->address,
                $existing ? null : 'A deposit address for this network is being provisioned. Please try again shortly.',
            ];
        }
    }

    /**
     * Display detail rows for a method (crypto address is shown separately with a QR).
     *
     * @return array<int, array{label: string, value: mixed}>
     */
    public static function methodDetails(DepositMethod $m): array
    {
        $isCrypto = $m->type === DepositMethodType::Crypto;

        return collect($m->details ?? [])
            ->reject(fn ($v, $k) => $isCrypto && $k === 'address')
            ->map(fn ($v, $k) => ['label' => Str::headline($k), 'value' => $v])
            ->values()->all();
    }
}
