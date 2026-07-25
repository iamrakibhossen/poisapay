<?php

declare(strict_types=1);

namespace App\Sell\Events;

use App\Sell\Models\RefundRequest;
use Illuminate\Database\Eloquent\Model;

/** Base for refund-request lifecycle events — subject + audit payload are shared. */
abstract class RefundRequestEvent extends SellDomainEvent
{
    public function __construct(public readonly RefundRequest $request) {}

    public function auditSubject(): ?Model
    {
        return $this->request;
    }

    public function auditData(): array
    {
        return [
            'order' => $this->request->order?->number,
            'amount' => (int) ($this->request->amount_refunded ?? $this->request->amount_requested),
            'type' => $this->request->type,
            'status' => $this->request->status->value,
            'reason' => $this->request->reason,
        ];
    }
}
