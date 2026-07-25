<?php

declare(strict_types=1);

namespace App\Sell\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sell\Actions\Order\PlaceOrder;
use App\Sell\Http\Requests\CheckoutRequest;
use App\Sell\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;

/** The buyer-facing checkout endpoint the PoisaPay payment page posts to. Thin. */
class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, PlaceOrder $action): JsonResponse
    {
        $order = $action->execute($request->user(), $request->toData());

        return OrderResource::make($order->load('items'))->response()->setStatusCode(201);
    }
}
