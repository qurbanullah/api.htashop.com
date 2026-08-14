<?php

namespace App\Http\Controllers\V1\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Product\ProductResource;
use App\Http\Resources\V1\Revision\RevisionResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Product\ProductService;
use App\Services\Revision\RevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductRevisionController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected RevisionService $revisionService,
    ) {
    }

    public function index(string $uuid, Request $request): JsonResponse
    {
        $product = $this->productService->searchByUuid($uuid);
        $this->authorize('view', $product);

        $revisions = $this->revisionService->read($product, $request->all());

        return ApiResponse::success(RevisionResource::collection($revisions), 'Product revisions retrieved successfully');
    }

    public function show(string $uuid, string $revisionUuid): JsonResponse
    {
        $product = $this->productService->searchByUuid($uuid);
        $this->authorize('view', $product);

        $revision = $this->revisionService->searchByUuid($revisionUuid);
        abort_unless($revision->revisable_type === get_class($product) && $revision->revisable_id === $product->id, 404);

        return ApiResponse::success(new RevisionResource($revision), 'Revision retrieved successfully');
    }

    public function restore(string $uuid, string $revisionUuid): JsonResponse
    {
        $product = $this->productService->searchByUuid($uuid);
        $this->authorize('update', $product);

        $revision = $this->revisionService->searchByUuid($revisionUuid);
        abort_unless($revision->revisable_type === get_class($product) && $revision->revisable_id === $product->id, 404);

        $restoredProduct = $this->revisionService->restore($revision);

        return ApiResponse::success(new ProductResource($restoredProduct), 'Product restored successfully');
    }
}
