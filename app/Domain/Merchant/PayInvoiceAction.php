<?php

declare(strict_types=1);

namespace App\Domain\Merchant;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Compliance\AccountGuard;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Enums\MerchantStatus;
use App\Events\InvoicePaid;
use App\Models\Merchant;
use App\Models\MerchantInvoice;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pay a merchant invoice (TDD §8.2 / QR). A ledger op that splits the gross into
 * the merchant's net (payer:available -> merchant:available) and the platform
 * processing fee (-> fee:income). Atomic, idempotent (one payment per invoice),
 * instant. Emits invoice.paid for webhooks/notifications.
 */
class PayInvoiceAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(User $payer, MerchantInvoice $invoice): MerchantInvoice
    {
        AccountGuard::assertActive($payer);

        return DB::transaction(function () use ($payer, $invoice): MerchantInvoice {
            $invoice = MerchantInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $invoice->loadMissing('asset');

            if ($invoice->status === 'paid') {
                return $invoice; // idempotent
            }
            if (! $invoice->isPayable()) {
                throw new RuntimeException('Invoice is no longer payable.');
            }
            if ($payer->id === $invoice->merchant_id) {
                throw new RuntimeException('A merchant cannot pay their own invoice.');
            }

            // A suspended merchant cannot take new payments (an active/absent profile is fine).
            $merchant = Merchant::where('user_id', $invoice->merchant_id)->first();
            if ($merchant && $merchant->status === MerchantStatus::Suspended) {
                throw new RuntimeException('This merchant is not currently able to accept payments.');
            }

            $asset = $invoice->asset;
            $amount = Money::ofBase($invoice->amount, $asset->decimals, $asset->symbol);

            // Processing fee (bps of gross), floored, and only when a fee is configured.
            $feeBps = $merchant?->feeBps() ?? (int) getSetting('merchant_fee_bps', 100);
            $fee = Money::ofBase(
                BigInteger::of($amount->baseString())->multipliedBy($feeBps)->dividedBy(10_000),
                $asset->decimals,
                $asset->symbol,
            );
            $net = $amount->minus($fee);

            $payerAcct = $this->accounts->forUser($payer, LedgerAccountType::UserAvailable, $invoice->asset_id);
            $merchantAcct = $this->accounts->forUser($invoice->merchant_id, LedgerAccountType::UserAvailable, $invoice->asset_id);
            $feeAcct = $this->accounts->system(LedgerAccountType::FeeIncome, $invoice->asset_id);

            $balanceRow = DB::table('account_balances')->where('account_id', $payerAcct->id)->lockForUpdate()->first();
            $current = Money::ofBase($balanceRow->balance ?? '0', $asset->decimals, $asset->symbol);
            if ($current->isLessThan($amount)) {
                throw new RuntimeException('Insufficient balance to pay this invoice.');
            }

            $lines = [
                PostingLine::debit($payerAcct->id, $invoice->asset_id, $amount),
                PostingLine::credit($merchantAcct->id, $invoice->asset_id, $net),
            ];
            if ($fee->isPositive()) {
                $lines[] = PostingLine::credit($feeAcct->id, $invoice->asset_id, $fee);
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'merchant.invoice.pay',
                idempotencyKey: "invoice:pay:{$invoice->id}",
                lines: $lines,
                memo: "Invoice {$invoice->reference}",
                metadata: ['invoice_id' => $invoice->id, 'payer_id' => $payer->id, 'fee_bps' => $feeBps],
            ));

            $invoice->update([
                'status' => 'paid',
                'payer_id' => $payer->id,
                'entry_id' => $entry->id,
                'fee_amount' => $fee->baseString(),
                'paid_at' => now(),
            ]);

            ActivityLogger::log('invoice.paid', $invoice);

            InvoicePaid::dispatch($invoice->id);

            return $invoice->refresh();
        });
    }
}
