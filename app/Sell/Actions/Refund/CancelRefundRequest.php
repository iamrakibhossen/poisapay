<?php

declare(strict_types=1);

namespace App\Sell\Actions\Refund;

use App\Sell\Enums\RefundRequestStatus;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\RefundRequest;

/** A buyer withdraws their own still-open (unactioned) refund request. */
class CancelRefundRequest
{
    public function execute(RefundRequest $request): RefundRequest
    {
        if (! $request->status->canTransitionTo(RefundRequestStatus::Cancelled)) {
            throw SellException::refundRequestClosed();
        }

        $request->update(['status' => RefundRequestStatus::Cancelled, 'resolved_at' => now()]);

        return $request;
    }
}
