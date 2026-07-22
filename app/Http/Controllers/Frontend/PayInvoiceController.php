<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Merchant\PayInvoiceAction;
use App\Domain\Wallet\WalletService;
use App\Http\Controllers\Controller;
use App\Models\MerchantInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Invoice payment page — server-rendered. The controller renders the invoice
 * details, payer balance and payability; the payment itself is a form POST handled
 * by {@see pay()} wrapping {@see PayInvoiceAction} and redirecting back. An unknown
 * invoice 404s. Money-critical — no balance math lives here.
 */
class PayInvoiceController extends Controller
{
    public function index(Request $request, string $invoice, WalletService $wallets): View
    {
        $model = MerchantInvoice::with('asset', 'merchant')->findOrFail($invoice);
        $user = $request->user();
        $isMerchant = $user->id === $model->merchant_id;

        $available = $isMerchant ? null : $wallets->balanceFor($user, $model->asset)->available;

        $expired = $model->status === 'expired'
            || ($model->status === 'pending' && $model->expires_at && $model->expires_at->isPast());

        $statusColor = match ($model->status) {
            'paid' => 'success',
            'pending' => 'warning',
            'cancelled' => 'danger',
            default => 'gray',
        };

        return view('frontend.pay-invoice', [
            'invoice' => $model,
            'symbol' => $model->asset->symbol,
            'amount' => $model->money()->format(),
            'merchantName' => $model->merchant->name,
            'reference' => $model->reference,
            'memo' => $model->memo,
            'status' => $model->status,
            'statusLabel' => ucfirst($model->status),
            'statusColor' => $statusColor,
            'paidAtHuman' => $model->paid_at?->diffForHumans(),
            'isMerchant' => $isMerchant,
            'isPayable' => $model->isPayable(),
            'isExpired' => $expired,
            'isCancelled' => $model->status === 'cancelled',
            'isPaid' => $model->status === 'paid',
            'available' => $available?->format(),
        ]);
    }

    public function pay(Request $request, string $invoice, PayInvoiceAction $payInvoice): RedirectResponse
    {
        $model = MerchantInvoice::with('asset', 'merchant')->findOrFail($invoice);
        $user = $request->user();

        if ($user->id === $model->merchant_id) {
            throw ValidationException::withMessages(['invoice' => 'You cannot pay your own invoice.']);
        }

        if (! $model->isPayable()) {
            throw ValidationException::withMessages(['invoice' => 'This invoice is no longer payable.']);
        }

        try {
            $paid = $payInvoice->execute($user, $model);
        } catch (\Throwable $e) {
            // Domain guard failures (insufficient funds, already paid, expired) surface as errors.
            throw ValidationException::withMessages(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('pay.invoice', $model->id)
            ->with('success', 'Paid '.$paid->money()->format().' to '.$paid->merchant->name.'.');
    }
}
