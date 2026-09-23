<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\Knowledge;

use App\Enums\KnowledgeStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Knowledge\StoreKnowledgeEntryRequest;
use App\Http\Requests\V1\Knowledge\UpdateKnowledgeEntryRequest;
use App\Http\Resources\V1\Knowledge\KnowledgeEntryCollection;
use App\Http\Resources\V1\Knowledge\KnowledgeEntryResource;
use App\Http\Responses\V1\ApiResponse;
use App\Models\KnowledgeEntry;
use App\Services\Knowledge\KnowledgeEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin CRUD for the assistant's knowledge base.
 */
class KnowledgeEntryAdminController extends Controller
{
    public function __construct(
        private KnowledgeEntryService $knowledgeEntryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $entries = $this->knowledgeEntryService
                ->search([
                    'search' => $request->input('search'),
                    'status' => $request->input('status'),
                    'locale' => $request->input('locale'),
                    'sort' => $request->input('sort'),
                    'order' => $request->input('order'),
                ])
                ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

            return ApiResponse::success(
                new KnowledgeEntryCollection($entries),
                'Knowledge entries retrieved successfully'
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to list knowledge entries', ['error' => $throwable->getMessage()]);

            return ApiResponse::error('Failed to retrieve knowledge entries', null, 500);
        }
    }

    public function statistics(): JsonResponse
    {
        try {
            return ApiResponse::success(
                $this->knowledgeEntryService->statistics(),
                'Knowledge base statistics retrieved successfully'
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to retrieve knowledge statistics', ['error' => $throwable->getMessage()]);

            return ApiResponse::error('Failed to retrieve knowledge base statistics', null, 500);
        }
    }

    public function show(string $uuid): JsonResponse
    {
        $entry = $this->findByUuid($uuid);

        if (! $entry) {
            return ApiResponse::error('Knowledge entry not found', null, 404);
        }

        return ApiResponse::success(
            new KnowledgeEntryResource($entry),
            'Knowledge entry retrieved successfully'
        );
    }

    public function store(StoreKnowledgeEntryRequest $request): JsonResponse
    {
        try {
            $entry = $this->knowledgeEntryService->create([
                ...$request->validated(),
                'created_by' => $request->user()?->id,
            ]);

            return ApiResponse::success(
                new KnowledgeEntryResource($entry),
                'Knowledge entry created successfully',
                201
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to create knowledge entry', ['error' => $throwable->getMessage()]);

            return ApiResponse::error('Failed to create the knowledge entry', null, 500);
        }
    }

    public function update(UpdateKnowledgeEntryRequest $request, string $uuid): JsonResponse
    {
        $entry = $this->findByUuid($uuid);

        if (! $entry) {
            return ApiResponse::error('Knowledge entry not found', null, 404);
        }

        try {
            $entry = $this->knowledgeEntryService->update($entry, $request->validated());

            return ApiResponse::success(
                new KnowledgeEntryResource($entry),
                'Knowledge entry updated successfully'
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to update knowledge entry', [
                'uuid' => $uuid,
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to update the knowledge entry', null, 500);
        }
    }

    public function destroy(string $uuid): JsonResponse
    {
        $entry = $this->findByUuid($uuid);

        if (! $entry) {
            return ApiResponse::error('Knowledge entry not found', null, 404);
        }

        try {
            $this->knowledgeEntryService->delete($entry);

            return ApiResponse::success(null, 'Knowledge entry deleted successfully');
        } catch (Throwable $throwable) {
            Log::error('Failed to delete knowledge entry', [
                'uuid' => $uuid,
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to delete the knowledge entry', null, 500);
        }
    }

    public function publish(string $uuid): JsonResponse
    {
        return $this->setStatus($uuid, KnowledgeStatusEnum::PUBLISHED->value);
    }

    public function unpublish(string $uuid): JsonResponse
    {
        return $this->setStatus($uuid, KnowledgeStatusEnum::DRAFT->value);
    }

    protected function setStatus(string $uuid, string $status): JsonResponse
    {
        $entry = $this->findByUuid($uuid);

        if (! $entry) {
            return ApiResponse::error('Knowledge entry not found', null, 404);
        }

        try {
            $entry = $this->knowledgeEntryService->update($entry, ['status' => $status]);

            return ApiResponse::success(
                new KnowledgeEntryResource($entry),
                $status === KnowledgeStatusEnum::PUBLISHED->value
                    ? 'Knowledge entry published successfully'
                    : 'Knowledge entry unpublished successfully'
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to change knowledge entry status', [
                'uuid' => $uuid,
                'error' => $throwable->getMessage(),
            ]);

            return ApiResponse::error('Failed to change the knowledge entry status', null, 500);
        }
    }

    protected function findByUuid(string $uuid): ?KnowledgeEntry
    {
        return KnowledgeEntry::query()->where('uuid', $uuid)->first();
    }
}
