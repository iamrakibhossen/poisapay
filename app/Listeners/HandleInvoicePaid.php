<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Webhook\WebhookService;
use App\Events\InvoicePaid;
use App\Models\MerchantInvoice;
use App\Notifications\LedgerEventNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleInvoicePaid implements ShouldQueue
{
    public function __construct(private readonly WebhookService $webhooks) {}

    public function handle(InvoicePaid $event): void
    {
        $invoice = MerchantInvoice::with('asset', 'merchant')->find($event->invoiceId);
        if (! $invoice) {
            return;
        }

        $invoice->merchant?->notify(new LedgerEventNotification(
            title: 'Invoice paid',
            body: "Invoice {$invoice->reference} was paid: {$invoice->money()->format()}.",
            event: 'invoice.paid',
            url: route('wallet'),
        ));

        // Merchant/integrator webhook (§8.3).
        $this->webhooks->dispatch($invoice->merchant_id, 'invoice.paid', [
            'invoice_id' => $invoice->id,
            'reference' => $invoice->reference,
            'asset' => $invoice->asset->symbol,
            'amount' => $invoice->money()->toDecimal(),
            'payer_id' => $invoice->payer_id,
        ]);
    }
}
