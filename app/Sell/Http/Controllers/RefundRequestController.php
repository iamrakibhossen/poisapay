<?php

declare(strict_types=1);

namespace App\Sell\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sell\Actions\Refund\CancelRefundRequest;
use App\Sell\Actions\Refund\EscalateRefundRequest;
use App\Sell\Actions\Refund\RequestRefund;
use App\Sell\Actions\Refund\ResolveRefundRequest;
use App\Sell\Http\Requests\RequestRefundRequest;
use App\Sell\Http\Requests\RespondRefundRequest;
use App\Sell\Http\Resources\RefundRequestResource;
use App\Sell\Models\Order;
use App\Sell\Models\RefundRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Buyer + seller refund-request API. Thin: authorize → Action → Resource. Buyers
 * open/cancel/escalate their requests; sellers approve/reject theirs. (Operators
 * use the admin controller.)
 */
class RefundRequestController extends Controller
{
    use AuthorizesRequests;

    /** The caller's refund requests — those they raised (buyer) or received (seller). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->getKey();

        $requests = RefundRequest::query()
            ->with('order')
            ->where(fn ($q) => $q->where('buyer_user_id', $userId)
                ->orWhereHas('seller', fn ($s) => $s->where('user_id', $userId)))
            ->latest()
            ->cursorPaginate(20);

        return RefundRequestResource::collection($requests);
    }

    public function show(Request $request, RefundRequest $refundRequest): RefundRequestResource
    {
        $this->authorize('view', $refundRequest);

        return RefundRequestResource::make($refundRequest->load('order'));
    }

    public function store(RequestRefundRequest $request, RequestRefund $action): JsonResponse
    {
        $order = Order::findOrFail($request->validated('order_id'));

        $refund = $action->execute(
            $order,
            $request->user(),
            $request->validated('type'),
            $request->validated('amount') !== null ? (int) $request->validated('amount') : null,
            (string) $request->validated('reason'),
        );

        return RefundRequestResource::make($refund->load('order'))->response()->setStatusCode(201);
    }

    public function approve(RespondRefundRequest $request, RefundRequest $refundRequest, ResolveRefundRequest $action): RefundRequestResource
    {
        $this->authorize('respond', $refundRequest);

        return RefundRequestResource::make($action->approve($refundRequest, $request->user(), $request->validated('note')));
    }

    public function reject(RespondRefundRequest $request, RefundRequest $refundRequest, ResolveRefundRequest $action): RefundRequestResource
    {
        $this->authorize('respond', $refundRequest);

        return RefundRequestResource::make($action->reject($refundRequest, $request->user(), $request->validated('note')));
    }

    public function escalate(Request $request, RefundRequest $refundRequest, EscalateRefundRequest $action): RefundRequestResource
    {
        $this->authorize('escalate', $refundRequest);

        return RefundRequestResource::make($action->execute($refundRequest));
    }

    public function cancel(Request $request, RefundRequest $refundRequest, CancelRefundRequest $action): RefundRequestResource
    {
        $this->authorize('cancel', $refundRequest);

        return RefundRequestResource::make($action->execute($refundRequest));
    }
}
