<?php

declare(strict_types=1);

namespace App\Sell\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sell\Actions\Product\CreateProduct;
use App\Sell\Actions\Product\SetProductStatus;
use App\Sell\Actions\Product\UpdateProduct;
use App\Sell\Enums\ProductStatus;
use App\Sell\Http\Requests\ProductRequest;
use App\Sell\Http\Resources\ProductResource;
use App\Sell\Models\Product;
use App\Sell\Models\Seller;
use App\Sell\Services\CatalogService;
use App\Sell\Services\SellerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Seller catalog. Thin: resolve seller → Action/Service → Resource. */
class ProductController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SellerService $sellers,
        private readonly CatalogService $catalog,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $seller = $this->approvedSeller($request);
        $page = $this->catalog->search(
            $seller,
            $request->query('q'),
            $request->only(['status', 'type']),
            (int) $request->integer('per_page', 20),
            $request->query('cursor'),
        );

        return ProductResource::collection($page);
    }

    public function store(ProductRequest $request, CreateProduct $action): JsonResponse
    {
        $product = $action->execute($this->approvedSeller($request), $request->toData());

        return ProductResource::make($product->load('variants'))->response()->setStatusCode(201);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $this->authorize('view', $product);

        return ProductResource::make($product->load('variants'));
    }

    public function update(ProductRequest $request, Product $product, UpdateProduct $action): ProductResource
    {
        $this->authorize('update', $product);

        return ProductResource::make($action->execute($product, $request->toData())->load('variants'));
    }

    public function destroy(Request $request, Product $product): Response
    {
        $this->authorize('delete', $product);
        $product->delete();

        return response()->noContent();
    }

    public function publish(Request $request, Product $product, SetProductStatus $action): ProductResource
    {
        $this->authorize('update', $product);

        return ProductResource::make($action->execute($product, ProductStatus::Published));
    }

    public function archive(Request $request, Product $product, SetProductStatus $action): ProductResource
    {
        $this->authorize('update', $product);

        return ProductResource::make($action->execute($product, ProductStatus::Archived));
    }

    private function approvedSeller(Request $request): Seller
    {
        $seller = $this->sellers->forUser($request->user());
        abort_unless($seller?->canSell(), 403, 'You are not an approved seller.');

        return $seller;
    }
}
