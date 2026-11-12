<?php

namespace App\Http\Controllers\V1\Dam;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Dam\IndexRequest;
use App\Http\Requests\V1\Dam\StoreRequest;
use App\Http\Requests\V1\Dam\UpdateRequest;
use App\Http\Responses\V1\ApiResponse;
use App\Models\DamCollection;
use App\Services\Dam\DamCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DamCollectionController extends Controller
{
    public function __construct(protected DamCollectionService $service) {}

    protected function validationError($validator): JsonResponse
    {
        return ApiResponse::error('Validation failed', $validator->errors(), 422);
    }

    public function index(IndexRequest $request): JsonResponse
    {
        return ApiResponse::success($this->service->list([
            'kind' => $request->input('kind'),
            'is_active' => $request->input('is_active'),
            'search' => $request->input('search'),
        ]));
    }

    public function store(StoreRequest $request): JsonResponse
    {

        try {
            return ApiResponse::success(
                $this->service->create($request->only([
                    'key',
                    'name',
                    'kind',
                    'description',
                    'metadata',
                    'is_active',
                    'is_system',
                ])),
                'DAM collection created successfully',
                201
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create DAM collection', ['error' => $e->getMessage()]);

            return ApiResponse::error('Failed to create DAM collection', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateRequest $request, DamCollection $damCollection): JsonResponse
    {

        try {
            return ApiResponse::success(
                $this->service->update($damCollection, $request->only([
                    'key',
                    'name',
                    'kind',
                    'description',
                    'metadata',
                    'is_active',
                    'is_system',
                ])),
                'DAM collection updated successfully'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to update DAM collection', ['error' => $e->getMessage()]);

            return ApiResponse::error('Failed to update DAM collection', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(DamCollection $damCollection): JsonResponse
    {
        try {
            $this->service->delete($damCollection);

            return ApiResponse::success(null, 'DAM collection deleted successfully');
        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            Log::error('Failed to delete DAM collection', ['error' => $e->getMessage()]);

            return ApiResponse::error('Failed to delete DAM collection', ['error' => $e->getMessage()], 500);
        }
    }
}
