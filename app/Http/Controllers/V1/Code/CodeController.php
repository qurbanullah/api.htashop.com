<?php

namespace App\Http\Controllers\V1\Code;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Code\CodeStoreRequest;
use App\Http\Requests\V1\Code\CodeUpdateRequest;
use App\Http\Resources\V1\Code\CodeResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Code;
use App\Services\Code\CodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodeController extends Controller
{
    public function __construct(protected CodeService $codeService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Code::class);
        return ApiResponse::success(CodeResource::collection($this->codeService->read($request->all())), 'Codes retrieved successfully');
    }

    public function store(CodeStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Code::class);
        return ApiResponse::success(new CodeResource($this->codeService->create($request->validated())), 'Code created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        $code = $this->codeService->searchById($id);
        $this->authorize('view', $code);
        return ApiResponse::success(new CodeResource($code), 'Code retrieved successfully');
    }

    public function update(CodeUpdateRequest $request, int $id): JsonResponse
    {
        $code = $this->codeService->searchById($id);
        $this->authorize('update', $code);
        return ApiResponse::success(new CodeResource($this->codeService->update($code, $request->validated())), 'Code updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $code = $this->codeService->searchById($id);
        $this->authorize('delete', $code);
        $this->codeService->delete($code);
        return ApiResponse::success(null, 'Code deleted successfully');
    }
}
