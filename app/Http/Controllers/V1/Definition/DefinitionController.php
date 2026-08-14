<?php

namespace App\Http\Controllers\V1\Definition;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Definition\DefinitionIndexRequest;
use App\Http\Requests\V1\Definition\DefinitionStoreRequest;
use App\Http\Requests\V1\Definition\DefinitionUpdateRequest;
use App\Http\Resources\V1\Definition\DefinitionResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Definition;
use App\Services\Definition\DefinitionService;
use Illuminate\Http\JsonResponse;

class DefinitionController extends Controller
{
    public function __construct(protected DefinitionService $definitionService)
    {
    }

    public function index(DefinitionIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Definition::class);

        return ApiResponse::success(DefinitionResource::collection($this->definitionService->read($request->validated())), 'Definitions retrieved successfully');
    }

    public function store(DefinitionStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Definition::class);

        return ApiResponse::success(new DefinitionResource($this->definitionService->create($request->validated())), 'Definition created successfully', 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $definition = $this->definitionService->searchByUuid($uuid);
        $this->authorize('view', $definition);

        return ApiResponse::success(new DefinitionResource($definition), 'Definition retrieved successfully');
    }

    public function update(DefinitionUpdateRequest $request, string $uuid): JsonResponse
    {
        $definition = $this->definitionService->searchByUuid($uuid);
        $this->authorize('update', $definition);

        return ApiResponse::success(new DefinitionResource($this->definitionService->update($definition, $request->validated())), 'Definition updated successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $definition = $this->definitionService->searchByUuid($uuid);
        $this->authorize('delete', $definition);
        $this->definitionService->delete($definition);

        return ApiResponse::success(null, 'Definition deleted successfully');
    }
}
