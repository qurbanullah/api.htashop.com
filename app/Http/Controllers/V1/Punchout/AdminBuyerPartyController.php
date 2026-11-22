<?php

namespace App\Http\Controllers\V1\Punchout;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Punchout\AdminBuyerPartyIndexRequest;
use App\Http\Requests\V1\Punchout\AdminBuyerPartyLinkRequest;
use App\Http\Requests\V1\Punchout\AdminBuyerPartyStoreRequest;
use App\Http\Requests\V1\Punchout\AdminBuyerPartyUpdateRequest;
use App\Http\Resources\V1\Punchout\BuyerPartyResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Punchout\BuyerPartyService;
use Illuminate\Http\JsonResponse;

/**
 * Platform-admin management of buyer parties.
 *
 * Tenants never call this: linking two tenant relationships to one buyer party
 * is a platform decision, and it must not leak one tenant's mapping to another.
 */
class AdminBuyerPartyController extends Controller
{
    public function __construct(
        protected BuyerPartyService $buyerPartyService,
    ) {}

    public function index(AdminBuyerPartyIndexRequest $request): JsonResponse
    {
        $parties = $this->buyerPartyService->read($request->validated());

        return ApiResponse::success([
            'items' => BuyerPartyResource::collection($parties->items()),
            'meta' => [
                'current_page' => $parties->currentPage(),
                'last_page' => $parties->lastPage(),
                'per_page' => $parties->perPage(),
                'total' => $parties->total(),
            ],
        ], 'Buyer parties retrieved successfully');
    }

    public function store(AdminBuyerPartyStoreRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new BuyerPartyResource($this->buyerPartyService->create($request->validated())),
            'Buyer party created successfully',
            201,
        );
    }

    public function show(string $uuid): JsonResponse
    {
        return ApiResponse::success(
            new BuyerPartyResource($this->buyerPartyService->find($uuid)),
            'Buyer party retrieved successfully',
        );
    }

    public function update(AdminBuyerPartyUpdateRequest $request, string $uuid): JsonResponse
    {
        $party = $this->buyerPartyService->find($uuid);

        return ApiResponse::success(
            new BuyerPartyResource($this->buyerPartyService->update($party, $request->validated())),
            'Buyer party updated successfully',
        );
    }

    public function link(AdminBuyerPartyLinkRequest $request, string $uuid): JsonResponse
    {
        $party = $this->buyerPartyService->find($uuid);

        return ApiResponse::success(
            $this->buyerPartyService->link(
                $party,
                $request->validated('buyer_uuids'),
                (bool) $request->validated('dry_run', false),
            ),
            'Buyer party link processed successfully',
        );
    }
}
