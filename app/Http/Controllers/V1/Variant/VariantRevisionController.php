<?php

namespace App\Http\Controllers\V1\Variant;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Revision\RevisionResource;
use App\Http\Resources\V1\Variant\VariantResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Revision\RevisionService;
use App\Services\Variant\VariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VariantRevisionController extends Controller
{
    public function __construct(
        protected VariantService $variantService,
        protected RevisionService $revisionService,
    ) {
    }

    public function index(string $uuid, Request $request): JsonResponse
    {
        $variant = $this->variantService->searchByUuid($uuid);
        $this->authorize('view', $variant);

        $revisions = $this->revisionService->read($variant, $request->all());

        return ApiResponse::success(RevisionResource::collection($revisions), 'Variant revisions retrieved successfully');
    }

    public function show(string $uuid, string $revisionUuid): JsonResponse
    {
        $variant = $this->variantService->searchByUuid($uuid);
        $this->authorize('view', $variant);

        $revision = $this->revisionService->searchByUuid($revisionUuid);
        abort_unless($revision->revisable_type === get_class($variant) && $revision->revisable_id === $variant->id, 404);

        return ApiResponse::success(new RevisionResource($revision), 'Revision retrieved successfully');
    }

    public function restore(string $uuid, string $revisionUuid): JsonResponse
    {
        $variant = $this->variantService->searchByUuid($uuid);
        $this->authorize('update', $variant);

        $revision = $this->revisionService->searchByUuid($revisionUuid);
        abort_unless($revision->revisable_type === get_class($variant) && $revision->revisable_id === $variant->id, 404);

        $restoredVariant = $this->revisionService->restore($revision);

        return ApiResponse::success(new VariantResource($restoredVariant), 'Variant restored successfully');
    }
}
