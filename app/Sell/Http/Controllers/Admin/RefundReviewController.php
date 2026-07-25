<?php

declare(strict_types=1);

namespace App\Sell\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Sell\Actions\Refund\ResolveRefundRequest;
use App\Sell\Enums\RefundRequestStatus;
use App\Sell\Http\Requests\RespondRefundRequest;
use App\Sell\Http\Resources\RefundRequestResource;
use App\Sell\Models\RefundRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Operator refund review API — resolve escalated (or any) refund requests. */
class RefundReviewController extends Controller
{
    use AuthorizesRequests;

    /** Refund queue, newest first — defaults to the escalated ones awaiting review. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->query('status', RefundRequestStatus::Escalated->value);

        $requests = RefundRequest::query()
            ->with('order')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->cursorPaginate(30);

        return RefundRequestResource::collection($requests);
    }

    public function approve(RespondRefundRequest $request, RefundRequest $refundRequest, ResolveRefundRequest $action): RefundRequestResource
    {
        $this->authorize('resolveAsAdmin', $refundRequest);

        return RefundRequestResource::make($action->approve($refundRequest, $request->user(), $request->validated('note')));
    }

    public function reject(RespondRefundRequest $request, RefundRequest $refundRequest, ResolveRefundRequest $action): RefundRequestResource
    {
        $this->authorize('resolveAsAdmin', $refundRequest);

        return RefundRequestResource::make($action->reject($refundRequest, $request->user(), $request->validated('note')));
    }
}
