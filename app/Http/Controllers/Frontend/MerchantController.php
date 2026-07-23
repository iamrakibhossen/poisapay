<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Merchant\CancelInvoiceAction;
use App\Domain\Merchant\CreateInvoiceAction;
use App\Domain\Merchant\RefundInvoiceAction;
use App\Domain\Merchant\RegisterMerchantAction;
use App\Enums\KycTier;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\MerchantInvoice;
use App\Support\Money;
use App\Support\Qr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Merchant console — server-rendered. The controller builds the merchant profile,
 * invoice list, stats and (payable) QR codes and hands them to the Blade view;
 * every mutation wraps its existing domain action and redirects back with a flash
 * message (or validation errors). All reads and writes are scoped to the
 * authenticated user / their merchant. Money-critical (invoice funds move via the
 * actions).
 */
class MerchantController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Balances are pooled per coin (USDT/USDC etc. exist as one Asset row per
        // network), so the merchant only picks a coin — collapse to one canonical
        // (lowest-id) row per symbol to avoid listing USDT/USDC once per chain.
        $assets = Asset::where('is_active', true)
            ->where('kind', 'crypto')
            ->orderBy('id')
            ->get()
            ->unique('symbol')
            ->sortBy(fn (Asset $a) => [$a->sort, $a->symbol])
            ->values();

        $allowRefunds = (bool) getSetting('merchant_allow_refunds', true);

        if (! $user->isMerchant()) {
            return view('frontend.merchant', [
                'isMerchant' => false,
                'featureEnabled' => feature('merchant_enabled'),
                'isFullKyc' => $user->tier() === KycTier::Full,
                'canRegister' => $user->tier() === KycTier::Full && feature('merchant_enabled'),
                'assets' => $assets,
                'allowRefunds' => $allowRefunds,
                'merchant' => null,
                'invoices' => collect(),
                'stats' => null,
            ]);
        }

        $merchant = $user->merchant;

        $base = MerchantInvoice::with(['asset', 'payer'])->where('merchant_id', $user->id);

        $invoices = (clone $base)->latest()->limit(50)->get()
            ->map(fn (MerchantInvoice $inv) => $this->invoiceRow($inv))
            ->values();

        $paid = (clone $base)->where('status', 'paid')->get();

        $netRevenue = $paid->groupBy('asset.symbol')->map(fn ($group) => $group->reduce(
            fn (?Money $carry, MerchantInvoice $inv) => $carry ? $carry->plus($inv->netMoney()) : $inv->netMoney(),
            null,
        ))->map(fn (Money $m) => $m->format())->values();

        $totalFees = $paid->groupBy('asset.symbol')->map(fn ($group) => $group->reduce(
            fn (?Money $carry, MerchantInvoice $inv) => $carry ? $carry->plus($inv->feeMoney()) : $inv->feeMoney(),
            null,
        ))->map(fn (Money $m) => $m->format())->values();

        $feePct = rtrim(rtrim(number_format($merchant->feeBps() / 100, 2), '0'), '.').'%';

        return view('frontend.merchant', [
            'isMerchant' => true,
            'featureEnabled' => feature('merchant_enabled'),
            'isFullKyc' => true,
            'canRegister' => false,
            'assets' => $assets,
            'allowRefunds' => $allowRefunds,
            'merchant' => $merchant,
            'feePct' => $feePct,
            'invoices' => $invoices,
            'stats' => [
                'netRevenue' => $netRevenue,
                'totalFees' => $totalFees,
                'totalInvoices' => (clone $base)->count(),
                'paidCount' => $paid->count(),
            ],
        ]);
    }

    public function register(Request $request, RegisterMerchantAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'businessName' => 'required|string|max:120',
            'category' => 'nullable|string|max:60',
            'website' => 'nullable|url|max:255',
            'supportEmail' => 'nullable|email|max:255',
            'settlementAssetId' => 'nullable|integer',
        ]);

        if ($request->user()->tier() !== KycTier::Full) {
            throw ValidationException::withMessages([
                'businessName' => 'Full identity verification is required to become a merchant.',
            ]);
        }

        try {
            $action->execute($request->user(), [
                'business_name' => trim($validated['businessName']),
                'category' => ($validated['category'] ?? '') !== '' ? $validated['category'] : null,
                'website' => ($validated['website'] ?? '') !== '' ? $validated['website'] : null,
                'support_email' => ($validated['supportEmail'] ?? '') !== '' ? $validated['supportEmail'] : null,
                'settlement_asset_id' => $validated['settlementAssetId'] ?? null,
            ]);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['businessName' => $e->getMessage()]);
        }

        return redirect()->route('merchant')->with('success', 'Your merchant profile is ready.');
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $merchant = $request->user()->merchant;
        if (! $merchant) {
            throw ValidationException::withMessages(['businessName' => 'You are not registered as a merchant.']);
        }

        $validated = $request->validate([
            'businessName' => 'required|string|max:120',
            'category' => 'nullable|string|max:60',
            'website' => 'nullable|url|max:255',
            'supportEmail' => 'nullable|email|max:255',
            'settlementAssetId' => 'nullable|integer',
        ]);

        $merchant->update([
            'business_name' => trim($validated['businessName']),
            'category' => ($validated['category'] ?? '') !== '' ? $validated['category'] : null,
            'website' => ($validated['website'] ?? '') !== '' ? $validated['website'] : null,
            'support_email' => ($validated['supportEmail'] ?? '') !== '' ? $validated['supportEmail'] : null,
            'settlement_asset_id' => $validated['settlementAssetId'] ?? null,
        ]);

        return redirect()->route('merchant')->with('success', 'Business profile updated.');
    }

    public function createInvoice(Request $request, CreateInvoiceAction $invoices): RedirectResponse
    {
        $validated = $request->validate([
            'assetId' => 'required|integer',
            'amount' => 'required|string',
            'reference' => 'nullable|string|max:64',
            'memo' => 'nullable|string|max:140',
        ]);

        $asset = Asset::where('is_active', true)->where('kind', 'crypto')->find($validated['assetId']);
        if (! $asset) {
            throw ValidationException::withMessages(['assetId' => 'Please choose a valid crypto asset.']);
        }

        try {
            $money = Money::ofDecimal($validated['amount'], $asset->decimals, $asset->symbol);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid amount.']);
        }

        if (! $money->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        $reference = trim((string) ($validated['reference'] ?? '')) !== ''
            ? trim($validated['reference'])
            : 'INV-'.Str::upper(Str::random(10));

        try {
            $invoice = $invoices->execute(
                merchant: $request->user(),
                asset: $asset,
                amount: $money,
                reference: $reference,
                memo: ($validated['memo'] ?? '') !== '' ? $validated['memo'] : null,
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        // firstOrCreate is idempotent by (merchant, reference): if it returned an existing
        // invoice, the reference is a duplicate — surface that instead of a false "created".
        if (! $invoice->wasRecentlyCreated) {
            throw ValidationException::withMessages([
                'reference' => 'You already have an invoice with reference “'.$reference.'”. Use a different reference.',
            ]);
        }

        return redirect()->route('merchant')->with('success', 'Invoice '.$reference.' created for '.$money->format().'.');
    }

    public function cancelInvoice(Request $request, string $id, CancelInvoiceAction $action): RedirectResponse
    {
        $invoice = $this->ownedInvoice($request, $id);

        try {
            $action->execute($invoice);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('merchant')->with('success', 'Invoice '.$invoice->reference.' cancelled.');
    }

    public function refundInvoice(Request $request, string $id, RefundInvoiceAction $action): RedirectResponse
    {
        $invoice = $this->ownedInvoice($request, $id);

        if (! getSetting('merchant_allow_refunds', true)) {
            throw ValidationException::withMessages(['invoice' => 'Refunds are currently disabled.']);
        }

        try {
            $action->execute($invoice);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('merchant')->with('success', 'Invoice '.$invoice->reference.' refunded.');
    }

    /** Resolve an invoice owned by the authenticated merchant (404 otherwise). */
    private function ownedInvoice(Request $request, string $id): MerchantInvoice
    {
        return MerchantInvoice::with('asset')
            ->where('merchant_id', $request->user()->id)
            ->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function invoiceRow(MerchantInvoice $inv): array
    {
        $statusColor = match ($inv->status) {
            'paid' => 'success',
            'pending' => 'warning',
            'refunded' => 'info',
            'cancelled' => 'danger',
            default => 'gray',
        };

        return [
            'id' => $inv->id,
            'reference' => $inv->reference,
            'memo' => $inv->memo,
            'symbol' => $inv->asset->symbol,
            'amount' => $inv->money()->format(),
            'fee' => $inv->feeMoney()->format(),
            'net' => $inv->netMoney()->format(),
            'status' => $inv->status,
            'statusLabel' => ucfirst($inv->status),
            'statusColor' => $statusColor,
            'payer' => $inv->payer?->name,
            'created' => $inv->created_at->diffForHumans(),
            'isPayable' => $inv->isPayable(),
            'payUrl' => url('/pay/'.$inv->id),
            'qrSvg' => $inv->isPayable() ? Qr::svg(url('/pay/'.$inv->id)) : null,
        ];
    }
}
