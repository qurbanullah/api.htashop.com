<?php

namespace App\Http\Controllers\V1\Value;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Value\ValueStoreRequest;
use App\Http\Requests\V1\Value\ValueUpdateRequest;
use App\Http\Resources\V1\Value\ValueResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Value;
use App\Services\Value\ValueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValueController extends Controller
{
    public function __construct(protected ValueService $valueService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Value::class);
        return ApiResponse::success(ValueResource::collection($this->valueService->read($request->all())), 'Values retrieved successfully');
    }

    public function store(ValueStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Value::class);
        return ApiResponse::success(new ValueResource($this->valueService->create($request->validated())), 'Value created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        $value = $this->valueService->searchById($id);
        $this->authorize('view', $value);
        return ApiResponse::success(new ValueResource($value), 'Value retrieved successfully');
    }

    public function update(ValueUpdateRequest $request, int $id): JsonResponse
    {
        $value = $this->valueService->searchById($id);
        $this->authorize('update', $value);
        return ApiResponse::success(new ValueResource($this->valueService->update($value, $request->validated())), 'Value updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $value = $this->valueService->searchById($id);
        $this->authorize('delete', $value);
        $this->valueService->delete($value);
        return ApiResponse::success(null, 'Value deleted successfully');
    }
}
