<?php

namespace App\Http\Controllers\V1\Punchout;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Punchout\PunchoutBuyerIndexRequest;
use App\Http\Requests\V1\Punchout\PunchoutBuyerOptionsRequest;
use App\Http\Requests\V1\Punchout\PunchoutBuyerRotateSecretRequest;
use App\Http\Requests\V1\Punchout\PunchoutBuyerStoreRequest;
use App\Http\Requests\V1\Punchout\PunchoutBuyerUpdateRequest;
use App\Http\Resources\V1\Punchout\PunchoutBuyerResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Punchout\PunchoutBuyerService;
use Illuminate\Http\JsonResponse;

class AdminPunchoutBuyerController extends Controller
{
    public function __construct(
        protected PunchoutBuyerService $punchoutBuyerService,
    ) {}

    public function index(PunchoutBuyerIndexRequest $request): JsonResponse
    {
        $buyers = $this->punchoutBuyerService->read($request->validated());

        return ApiResponse::success([
            'items' => PunchoutBuyerResource::collection($buyers->items()),
            'meta' => [
                'current_page' => $buyers->currentPage(),
                'last_page' => $buyers->lastPage(),
                'per_page' => $buyers->perPage(),
                'total' => $buyers->total(),
            ],
        ], 'Punchout buyers retrieved successfully');
    }

    public function options(PunchoutBuyerOptionsRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->punchoutBuyerService->options($request->validated()),
            'Punchout buyer options retrieved successfully',
        );
    }

    public function store(PunchoutBuyerStoreRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new PunchoutBuyerResource($this->punchoutBuyerService->create($request->validated())),
            'Punchout buyer created successfully',
            201,
        );
    }

    public function show(string $uuid): JsonResponse
    {
        return ApiResponse::success(
            new PunchoutBuyerResource($this->punchoutBuyerService->searchByUuid($uuid)),
            'Punchout buyer retrieved successfully',
        );
    }

    public function update(PunchoutBuyerUpdateRequest $request, string $uuid): JsonResponse
    {
        $buyer = $this->punchoutBuyerService->searchByUuid($uuid);

        return ApiResponse::success(
            new PunchoutBuyerResource($this->punchoutBuyerService->update($buyer, $request->validated())),
            'Punchout buyer updated successfully',
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $buyer = $this->punchoutBuyerService->searchByUuid($uuid);
        $this->punchoutBuyerService->delete($buyer);

        return ApiResponse::success(null, 'Punchout buyer deleted successfully');
    }

    public function rotateSecret(PunchoutBuyerRotateSecretRequest $request, string $uuid): JsonResponse
    {
        $buyer = $this->punchoutBuyerService->searchByUuid($uuid);

        return ApiResponse::success(
            new PunchoutBuyerResource($this->punchoutBuyerService->rotateSecret(
                $buyer,
                (string) $request->validated('shared_secret'),
                (bool) $request->validated('keep_previous', true),
            )),
            'Punchout buyer secret rotated successfully',
        );
    }
}
