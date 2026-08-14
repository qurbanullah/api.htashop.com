<?php

namespace App\Http\Controllers\V1\Assignment;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Assignment\AssignmentStoreRequest;
use App\Http\Requests\V1\Assignment\AssignmentUpdateRequest;
use App\Http\Resources\V1\Assignment\AssignmentResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\Assignment;
use App\Services\Assignment\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(protected AssignmentService $assignmentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Assignment::class);

        return ApiResponse::success(AssignmentResource::collection($this->assignmentService->read($request->all())), 'Assignments retrieved successfully');
    }

    public function store(AssignmentStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Assignment::class);

        return ApiResponse::success(new AssignmentResource($this->assignmentService->create($request->validated())), 'Assignment created successfully', 201);
    }

    public function show(int $id): JsonResponse
    {
        $assignment = $this->assignmentService->searchById($id);
        $this->authorize('view', $assignment);

        return ApiResponse::success(new AssignmentResource($assignment), 'Assignment retrieved successfully');
    }

    public function update(AssignmentUpdateRequest $request, int $id): JsonResponse
    {
        $assignment = $this->assignmentService->searchById($id);
        $this->authorize('update', $assignment);

        return ApiResponse::success(new AssignmentResource($this->assignmentService->update($assignment, $request->validated())), 'Assignment updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $assignment = $this->assignmentService->searchById($id);
        $this->authorize('delete', $assignment);

        $this->assignmentService->delete($assignment);

        return ApiResponse::success(null, 'Assignment deleted successfully');
    }
}
