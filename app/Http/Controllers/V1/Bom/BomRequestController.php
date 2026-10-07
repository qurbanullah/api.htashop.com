<?php

namespace App\Http\Controllers\V1\Bom;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Bom\SubmitBomRequestRequest;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Bom\BomRequestService;
use Illuminate\Http\JsonResponse;

class BomRequestController extends Controller
{
    public function __construct(
        protected BomRequestService $service,
    ) {}

    /**
     * Submit a bill-of-materials sourcing request (public).
     */
    public function store(SubmitBomRequestRequest $request): JsonResponse
    {
        $bomRequest = $this->service->create($request->validated());

        return ApiResponse::success([
            'uuid' => $bomRequest->uuid,
            'reference_number' => 'BOM-'.$bomRequest->id,
            'status' => $bomRequest->status,
        ], 'BOM request submitted successfully. We will get back to you soon.', 201);
    }

    /**
     * Track a request by its UUID (public, no contact details exposed).
     */
    public function show(string $uuid): JsonResponse
    {
        $bomRequest = $this->service->show($uuid);

        if (! $bomRequest) {
            return ApiResponse::error('BOM request not found', null, 404);
        }

        return ApiResponse::success([
            'uuid' => $bomRequest->uuid,
            'reference_number' => 'BOM-'.$bomRequest->id,
            'status' => $bomRequest->status,
            'created_at' => $bomRequest->created_at,
        ], 'BOM request retrieved successfully');
    }
}
