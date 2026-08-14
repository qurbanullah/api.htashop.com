<?php

namespace App\Http\Controllers\V1\Label;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Label\LabelStoreRequest;
use App\Http\Requests\V1\Label\LabelUpdateRequest;
use App\Http\Resources\V1\Label\LabelResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Label;
use App\Services\Label\LabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function __construct(protected LabelService $labelService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Label::class);

        return ApiResponse::success(LabelResource::collection($this->labelService->read($request->all())), 'Labels retrieved successfully');
    }

    public function store(LabelStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Label::class);

        return ApiResponse::success(new LabelResource($this->labelService->create($request->validated())), 'Label created successfully', 201);
    }

    public function update(LabelUpdateRequest $request, string $uuid): JsonResponse
    {
        $label = $this->labelService->searchByUuid($uuid);
        $this->authorize('update', $label);

        return ApiResponse::success(new LabelResource($this->labelService->update($label, $request->validated())), 'Label updated successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $label = $this->labelService->searchByUuid($uuid);
        $this->authorize('delete', $label);
        $this->labelService->delete($label);

        return ApiResponse::success(null, 'Label deleted successfully');
    }
}
