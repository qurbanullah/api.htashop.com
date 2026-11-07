<?php

namespace App\Http\Controllers\V1\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Shipping\StorePackagingTemplateRequest;
use App\Http\Requests\V1\Shipping\UpdatePackagingTemplateRequest;
use App\Http\Resources\V1\Shipping\PackagingTemplateResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\PackagingTemplate;
use App\Services\Shipping\PackagingTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackagingTemplateController extends Controller
{
    public function __construct(protected PackagingTemplateService $templates) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PackagingTemplate::class);

        return ApiResponse::success(
            PackagingTemplateResource::collection($this->templates->read($request->all())),
            'Packaging templates retrieved successfully',
        );
    }

    public function store(StorePackagingTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', PackagingTemplate::class);

        $template = $this->templates->create($request->validated());

        return ApiResponse::success(
            new PackagingTemplateResource($template),
            'Packaging template created successfully',
            201,
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $template = $this->templates->searchByUuid($uuid);
        $this->authorize('view', $template);

        return ApiResponse::success(
            new PackagingTemplateResource($template),
            'Packaging template retrieved successfully',
        );
    }

    public function update(UpdatePackagingTemplateRequest $request, string $uuid): JsonResponse
    {
        $template = $this->templates->searchByUuid($uuid);
        $this->authorize('update', $template);

        $template = $this->templates->update($template, $request->validated());

        return ApiResponse::success(
            new PackagingTemplateResource($template),
            'Packaging template updated successfully',
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $template = $this->templates->searchByUuid($uuid);
        $this->authorize('delete', $template);

        $this->templates->delete($template);

        return ApiResponse::success(null, 'Packaging template deleted successfully');
    }
}
