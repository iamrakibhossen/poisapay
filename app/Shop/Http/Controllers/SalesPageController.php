<?php

declare(strict_types=1);

namespace App\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shop\Actions\SalesPage\CreateSalesPage;
use App\Shop\Actions\SalesPage\SetSalesPageStatus;
use App\Shop\Actions\SalesPage\UpdateSalesPage;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Http\Requests\SalesPageRequest;
use App\Shop\Http\Resources\SalesPageResource;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Shop\Services\SellerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Seller sales-page builder API. Thin: resolve seller → Action → Resource. */
class SalesPageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly SellerService $sellers) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $seller = $this->approvedSeller($request);

        return SalesPageResource::collection(
            SalesPage::where('seller_id', $seller->getKey())->latest()->cursorPaginate(20),
        );
    }

    public function store(SalesPageRequest $request, CreateSalesPage $action): JsonResponse
    {
        $page = $action->execute($this->approvedSeller($request), $request->toData());

        return SalesPageResource::make($page)->response()->setStatusCode(201);
    }

    public function show(Request $request, SalesPage $salesPage): SalesPageResource
    {
        $this->authorize('view', $salesPage);

        return SalesPageResource::make($salesPage);
    }

    public function update(SalesPageRequest $request, SalesPage $salesPage, UpdateSalesPage $action): SalesPageResource
    {
        $this->authorize('update', $salesPage);

        return SalesPageResource::make($action->execute($salesPage, $request->toData()));
    }

    public function destroy(Request $request, SalesPage $salesPage): Response
    {
        $this->authorize('delete', $salesPage);
        $salesPage->delete();

        return response()->noContent();
    }

    public function publish(Request $request, SalesPage $salesPage, SetSalesPageStatus $action): SalesPageResource
    {
        $this->authorize('update', $salesPage);

        return SalesPageResource::make($action->execute($salesPage, SalesPageStatus::Published));
    }

    public function archive(Request $request, SalesPage $salesPage, SetSalesPageStatus $action): SalesPageResource
    {
        $this->authorize('update', $salesPage);

        return SalesPageResource::make($action->execute($salesPage, SalesPageStatus::Archived));
    }

    private function approvedSeller(Request $request): Seller
    {
        $seller = $this->sellers->forUser($request->user());
        abort_unless($seller?->canSell(), 403, 'You are not an approved seller.');

        return $seller;
    }
}
