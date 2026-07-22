<?php

declare(strict_types=1);

namespace App\Domain\Merchant;

use App\Domain\Audit\ActivityLogger;
use App\Models\MerchantInvoice;
use RuntimeException;

/** Cancel an unpaid invoice (TDD §8.2). A pure state change — no money moves. */
class CancelInvoiceAction
{
    public function execute(MerchantInvoice $invoice): MerchantInvoice
    {
        if ($invoice->status === 'cancelled') {
            return $invoice;
        }
        if ($invoice->status !== 'pending') {
            throw new RuntimeException('Only a pending invoice can be cancelled.');
        }

        $invoice->update(['status' => 'cancelled']);
        ActivityLogger::log('invoice.cancelled', $invoice);

        return $invoice->refresh();
    }
}
