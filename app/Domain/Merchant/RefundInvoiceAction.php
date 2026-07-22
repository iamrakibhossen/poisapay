<?php

declare(strict_types=1);

namespace App\Domain\Merchant;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\MerchantInvoice;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Full refund of a paid invoice (TDD §8.2). The payer is made whole for the
 * gross: the merchant returns their net and the platform returns the processing
 * fee. Idempotent per invoice; refuses if the merchant lacks the net balance.
 */
class RefundInvoiceAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(MerchantInvoice $invoice): MerchantInvoice
    {
        return DB::transaction(function () use ($invoice): MerchantInvoice {
            $invoice = MerchantInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $invoice->loadMissing('asset');

            if ($invoice->status === 'refunded') {
                return $invoice; // idempotent
            }
            if ($invoice->status !== 'paid' || ! $invoice->payer_id) {
                throw new RuntimeException('Only a paid invoice can be refunded.');
            }

            $asset = $invoice->asset;
            $gross = Money::ofBase($invoice->amount, $asset->decimals, $asset->symbol);
            $fee = Money::ofBase($invoice->fee_amount ?? '0', $asset->decimals, $asset->symbol);
            $net = $gross->minus($fee);

            $merchantAcct = $this->accounts->forUser($invoice->merchant_id, LedgerAccountType::UserAvailable, $invoice->asset_id);
            $payerAcct = $this->accounts->forUser($invoice->payer_id, LedgerAccountType::UserAvailable, $invoice->asset_id);
            $feeAcct = $this->accounts->system(LedgerAccountType::FeeIncome, $invoice->asset_id);

            $merchantBal = DB::table('account_balances')->where('account_id', $merchantAcct->id)->lockForUpdate()->first();
            $available = Money::ofBase($merchantBal->balance ?? '0', $asset->decimals, $asset->symbol);
            if ($available->isLessThan($net)) {
                throw new RuntimeException('The merchant balance is insufficient to refund this invoice.');
            }

            $lines = [PostingLine::debit($merchantAcct->id, $invoice->asset_id, $net)];
            if ($fee->isPositive()) {
                $lines[] = PostingLine::debit($feeAcct->id, $invoice->asset_id, $fee);
            }
            $lines[] = PostingLine::credit($payerAcct->id, $invoice->asset_id, $gross);

            $entry = $this->ledger->post(new EntryData(
                type: 'merchant.invoice.refund',
                idempotencyKey: "invoice:refund:{$invoice->id}",
                lines: $lines,
                memo: "Refund {$invoice->reference}",
                metadata: ['invoice_id' => $invoice->id],
            ));

            $invoice->update(['status' => 'refunded', 'entry_id' => $entry->id]);
            ActivityLogger::log('invoice.refunded', $invoice);

            return $invoice->refresh();
        });
    }
}
