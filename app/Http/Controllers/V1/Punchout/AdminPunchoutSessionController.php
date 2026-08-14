<?php

namespace App\Http\Controllers\V1\Punchout;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Punchout\AdminPunchoutSessionIndexRequest;
use App\Http\Resources\V1\Punchout\PunchoutSessionAuditResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Punchout\PunchoutSessionService;
use Illuminate\Http\JsonResponse;

class AdminPunchoutSessionController extends Controller
{
    public function __construct(
        protected PunchoutSessionService $punchoutSessionService,
    ) {
    }

    public function index(AdminPunchoutSessionIndexRequest $request): JsonResponse
    {
        $sessions = $this->punchoutSessionService->read($request->validated());

        return ApiResponse::success([
            'items' => PunchoutSessionAuditResource::collection($sessions->items()),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ], 'Punchout sessions retrieved successfully');
    }

    public function show(string $uuid): JsonResponse
    {
        $session = $this->punchoutSessionService->searchByUuid($uuid);

        return ApiResponse::success(
            new PunchoutSessionAuditResource($session),
            'Punchout session retrieved successfully'
        );
    }
}
