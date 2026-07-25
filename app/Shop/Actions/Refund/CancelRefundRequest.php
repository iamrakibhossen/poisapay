<?php

declare(strict_types=1);

namespace App\Shop\Actions\Refund;

use App\Shop\Enums\RefundRequestStatus;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\RefundRequest;

/** A buyer withdraws their own still-open (unactioned) refund request. */
class CancelRefundRequest
{
    public function execute(RefundRequest $request): RefundRequest
    {
        if (! $request->status->canTransitionTo(RefundRequestStatus::Cancelled)) {
            throw ShopException::refundRequestClosed();
        }

        $request->update(['status' => RefundRequestStatus::Cancelled, 'resolved_at' => now()]);

        return $request;
    }
}
