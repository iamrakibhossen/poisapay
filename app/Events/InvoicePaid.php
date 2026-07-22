<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\MerchantInvoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePaid implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $invoiceId) {}

    public function broadcastOn(): array
    {
        $invoice = MerchantInvoice::find($this->invoiceId);
        if (! $invoice) {
            return [];
        }

        return array_values(array_filter([
            new PrivateChannel("user.{$invoice->merchant_id}"),
            $invoice->payer_id ? new PrivateChannel("user.{$invoice->payer_id}") : null,
        ]));
    }

    public function broadcastAs(): string
    {
        return 'invoice.paid';
    }
}
