<?php

namespace App\Http\Controllers\V1\Punchout;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Punchout\PunchoutBuyerPartNumberImportRequest;
use App\Http\Requests\V1\Punchout\PunchoutBuyerPartNumberIndexRequest;
use App\Http\Requests\V1\Punchout\PunchoutBuyerPartNumberStoreRequest;
use App\Http\Requests\V1\Punchout\PunchoutBuyerPartNumberUpdateRequest;
use App\Http\Resources\V1\Punchout\BuyerPartNumberResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Punchout\BuyerPartNumberImportService;
use App\Services\Punchout\BuyerPartNumberService;
use App\Services\Punchout\PunchoutBuyerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AdminPunchoutBuyerPartNumberController extends Controller
{
    public function __construct(
        protected BuyerPartNumberService $buyerPartNumberService,
        protected BuyerPartNumberImportService $buyerPartNumberImportService,
        protected PunchoutBuyerService $punchoutBuyerService,
    ) {}

    public function index(PunchoutBuyerPartNumberIndexRequest $request, string $buyer): JsonResponse
    {
        $relation = $this->punchoutBuyerService->searchByUuid($buyer);
        $mappings = $this->buyerPartNumberService->read($relation, $request->validated());

        return ApiResponse::success([
            'items' => BuyerPartNumberResource::collection($mappings->items()),
            'meta' => [
                'current_page' => $mappings->currentPage(),
                'last_page' => $mappings->lastPage(),
                'per_page' => $mappings->perPage(),
                'total' => $mappings->total(),
            ],
        ], 'Buyer part numbers retrieved successfully');
    }

    public function store(PunchoutBuyerPartNumberStoreRequest $request, string $buyer): JsonResponse
    {
        $relation = $this->punchoutBuyerService->searchByUuid($buyer);

        return ApiResponse::success(
            new BuyerPartNumberResource($this->buyerPartNumberService->create($relation, $request->validated())),
            'Buyer part number created successfully',
            201,
        );
    }

    public function update(PunchoutBuyerPartNumberUpdateRequest $request, string $buyer, string $uuid): JsonResponse
    {
        $relation = $this->punchoutBuyerService->searchByUuid($buyer);
        $mapping = $this->buyerPartNumberService->find($relation, $uuid);

        return ApiResponse::success(
            new BuyerPartNumberResource($this->buyerPartNumberService->update($relation, $mapping, $request->validated())),
            'Buyer part number updated successfully',
        );
    }

    public function destroy(string $buyer, string $uuid): JsonResponse
    {
        $relation = $this->punchoutBuyerService->searchByUuid($buyer);
        $mapping = $this->buyerPartNumberService->find($relation, $uuid);
        $this->buyerPartNumberService->delete($mapping);

        return ApiResponse::success(null, 'Buyer part number deleted successfully');
    }

    public function import(PunchoutBuyerPartNumberImportRequest $request, string $buyer): JsonResponse
    {
        $relation = $this->punchoutBuyerService->searchByUuid($buyer);

        $report = $this->buyerPartNumberImportService->handle($relation, $request->file('file'), [
            'dry_run' => $request->boolean('dry_run', true),
            'remap' => $request->boolean('remap'),
            'apply_suggestions' => $request->boolean('apply_suggestions'),
        ]);

        return ApiResponse::success(
            $report,
            $report['dry_run'] ? 'Import preview generated successfully' : 'Import applied successfully',
        );
    }

    public function template(): Response
    {
        return $this->csvResponse();
    }

    private function csvResponse(): Response
    {
        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, BuyerPartNumberImportService::templateColumns(), ',', '"', '');
        fputcsv($stream, [
            '1000048217', 'HSRY8VSZCP', '', 'MPU6050 MODULE',
            'Six axis motion tracking module, I2C interface', 'EA', 'MPU-6050', '', 'true', '', '',
        ], ',', '"', '');

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="buyer-part-numbers-template.csv"',
        ]);
    }
}
