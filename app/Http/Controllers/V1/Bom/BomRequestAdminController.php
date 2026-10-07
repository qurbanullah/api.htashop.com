<?php

namespace App\Http\Controllers\V1\Bom;

use App\Enums\BomRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Bom\BomRequestResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Bom\BomRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BomRequestAdminController extends Controller
{
    public function __construct(
        protected BomRequestService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->read($request->all());

        return ApiResponse::success([
            'data' => BomRequestResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], 'BOM requests retrieved successfully');
    }

    public function statistics(): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getStatistics(),
            'BOM request statistics retrieved successfully'
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $bomRequest = $this->service->show($uuid);

        if (! $bomRequest) {
            return ApiResponse::error('BOM request not found', null, 404);
        }

        return ApiResponse::success(
            new BomRequestResource($bomRequest),
            'BOM request retrieved successfully'
        );
    }

    public function updateStatus(string $uuid, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(BomRequestStatus::ALL)],
        ]);

        $bomRequest = $this->service->show($uuid);

        if (! $bomRequest) {
            return ApiResponse::error('BOM request not found', null, 404);
        }

        $updated = $this->service->updateStatus($bomRequest, (string) $data['status']);

        return ApiResponse::success(
            new BomRequestResource($updated),
            'BOM request status updated'
        );
    }
}
