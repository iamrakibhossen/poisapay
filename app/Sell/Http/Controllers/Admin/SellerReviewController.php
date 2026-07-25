<?php

declare(strict_types=1);

namespace App\Sell\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Sell\Actions\Seller\SetSellerStatus;
use App\Sell\Enums\SellerStatus;
use App\Sell\Http\Resources\SellerResource;
use App\Sell\Models\Seller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/** Operator review of seller applications. Thin: authorize → Action → Resource. */
class SellerReviewController extends Controller
{
    use AuthorizesRequests;

    public function approve(Request $request, Seller $seller, SetSellerStatus $action): JsonResponse
    {
        return $this->transition($request, $seller, SellerStatus::Approved, $action);
    }

    public function reject(Request $request, Seller $seller, SetSellerStatus $action): JsonResponse
    {
        return $this->transition($request, $seller, SellerStatus::Rejected, $action);
    }

    public function suspend(Request $request, Seller $seller, SetSellerStatus $action): JsonResponse
    {
        return $this->transition($request, $seller, SellerStatus::Suspended, $action);
    }

    private function transition(Request $request, Seller $seller, SellerStatus $to, SetSellerStatus $action): JsonResponse
    {
        $this->authorize('review', $seller);

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        $seller = $action->execute($seller, $to, $request->user(), $reason);

        return SellerResource::make($seller)->response();
    }
}
