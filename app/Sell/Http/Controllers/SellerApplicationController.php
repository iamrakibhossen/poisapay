<?php

declare(strict_types=1);

namespace App\Sell\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sell\Actions\Seller\SubmitSellerApplication;
use App\Sell\Http\Requests\SubmitSellerApplicationRequest;
use App\Sell\Http\Resources\SellerResource;
use Illuminate\Http\JsonResponse;

/** Thin: validate → hand a DTO to the Action → return a Resource. */
class SellerApplicationController extends Controller
{
    public function store(SubmitSellerApplicationRequest $request, SubmitSellerApplication $action): JsonResponse
    {
        $seller = $action->execute($request->user(), $request->toData());

        return SellerResource::make($seller)
            ->response()
            ->setStatusCode(201);
    }
}
