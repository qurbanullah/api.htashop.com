<?php

namespace App\Http\Controllers\V1\Product;

use App\Enums\ProductImportKind;
use App\Enums\ProductImportRowStatus;
use App\Enums\ProductImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Product\ProductImportIndexRequest;
use App\Http\Requests\V1\Product\ProductImportRowsRequest;
use App\Http\Requests\V1\Product\ProductImportStoreRequest;
use App\Http\Resources\V1\Product\ProductImportResource;
use App\Http\Resources\V1\Product\ProductImportRowResource;
use App\Http\Responses\V1\ApiResponse;
use App\Jobs\Import\ApplyProductImportChunkJob;
use App\Models\ProductImport;
use App\Services\Import\ProductImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Bulk product uploads.
 *
 * The file is parsed (validated and staged) inside the upload request — that is
 * the only step that needs the file itself. Applying the staged rows is queued
 * in bounded chunks, so a large catalogue never runs inside one request and the
 * progress of the apply step is observable.
 */
class ProductImportController extends Controller
{
    public function __construct(
        protected ProductImportService $imports,
    ) {}

    public function index(ProductImportIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ProductImport::class);

        $filters = $request->validated();
        $user = $request->user();

        $query = ProductImport::query()
            ->with('user')
            ->withCount($this->rowCounts())
            ->orderByDesc('id');

        if (! $user->isAdmin()) {
            $query->whereIn('organization_id', $user->activeOrganizationIds());
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['organization_id'] ?? null)) {
            $query->where('organization_id', (int) $filters['organization_id']);
        }

        $imports = $query->paginate((int) ($filters['per_page'] ?? 20));

        return ApiResponse::success([
            'data' => ProductImportResource::collection($imports->items())->resolve(),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'last_page' => $imports->lastPage(),
                'per_page' => $imports->perPage(),
                'total' => $imports->total(),
                'from' => $imports->firstItem(),
                'to' => $imports->lastItem(),
            ],
        ], 'Imports retrieved successfully');
    }

    public function store(ProductImportStoreRequest $request): JsonResponse
    {
        $this->authorize('create', ProductImport::class);

        $target = $this->imports->resolveTarget(
            $request->filled('tenant_id') ? (int) $request->input('tenant_id') : null,
            $request->filled('organization_id') ? (int) $request->input('organization_id') : null,
        );

        $file = $request->file('file');
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'ods'], true)) {
            // Some clients send no extension; fall back to the detected type so
            // the stored name always carries a readable format.
            $extension = (string) ($file->extension() ?: 'csv');
        }

        $path = "imports/{$uuid}.{$extension}";

        $stream = fopen($file->getRealPath(), 'rb');
        Storage::disk('local')->put($path, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        $import = $this->imports->createRecord([
            'uuid' => $uuid,
            'tenant_id' => $target['tenant_id'],
            'organization_id' => $target['organization_id'],
            'user_id' => $request->user()->id,
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'kind' => (string) ($request->input('kind') ?: ProductImportKind::PRODUCTS),
            'size_bytes' => (int) $file->getSize(),
            'options' => [
                'create_missing' => $request->boolean('create_missing'),
                'warehouse' => $request->input('warehouse') ?: null,
                'sheet' => $request->input('sheet') ?: null,
                'dry_run' => $request->boolean('dry_run'),
                // Images are downloaded unless the caller explicitly opts out.
                'download_images' => $request->has('download_images')
                    ? $request->boolean('download_images')
                    : true,
            ],
        ]);

        try {
            $this->imports->parse($import);
        } catch (Throwable $exception) {
            // The record stays as the audit trail of the rejected upload.
            return ApiResponse::error(
                'The file could not be read: '.$exception->getMessage(),
                new ProductImportResource($this->withCounts($import->refresh())),
                422,
            );
        }

        $import->refresh();

        if ($request->boolean('dry_run')) {
            $this->imports->completeDryRun($import);
        }

        return ApiResponse::success(
            new ProductImportResource($this->withCounts($import->refresh())),
            'File validated. Review the row report, then commit the import.',
            201,
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $import = $this->findImport($uuid);
        $this->authorize('view', $import);

        return ApiResponse::success(
            new ProductImportResource($this->withCounts($import->load('user'))),
            'Import retrieved successfully',
        );
    }

    public function rows(ProductImportRowsRequest $request, string $uuid): JsonResponse
    {
        $import = $this->findImport($uuid);
        $this->authorize('view', $import);

        $filters = $request->validated();

        $query = $import->rows()
            ->with('product:id,uuid,name,sku')
            ->orderBy('row_number');

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['external_id'] ?? null)) {
            $query->where('external_id', 'like', '%'.$filters['external_id'].'%');
        }

        $rows = $query->paginate((int) ($filters['per_page'] ?? 50));

        return ApiResponse::success([
            'data' => ProductImportRowResource::collection($rows->items())->resolve(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
        ], 'Import rows retrieved successfully');
    }

    /**
     * Queue the staged rows for application.
     *
     * Committing an import that has nothing left to apply is a client error, not
     * a silent no-op: it means the operator expected rows to be written.
     */
    public function commit(Request $request, string $uuid): JsonResponse
    {
        $import = $this->findImport($uuid);
        $this->authorize('update', $import);

        if (in_array($import->status, [ProductImportStatus::PARSING, ProductImportStatus::IMPORTING], true)) {
            return ApiResponse::error('This import is still running. Wait for it to finish or cancel it.', null, 409);
        }

        if ($import->status === ProductImportStatus::CANCELLED) {
            return ApiResponse::error('This import was cancelled. Upload the file again to import it.', null, 409);
        }

        $valid = $import->rows()->where('status', ProductImportRowStatus::VALID)->count();

        if ($valid === 0) {
            return ApiResponse::error(
                'There are no valid rows left to import. Fix the file and upload it again.',
                null,
                422,
            );
        }

        $this->imports->beginApply($import);
        ApplyProductImportChunkJob::dispatch($import->id)->onQueue(ApplyProductImportChunkJob::queueName());

        return ApiResponse::success(
            new ProductImportResource($this->withCounts($import->refresh())),
            "Import queued: {$valid} rows will be applied.",
            202,
        );
    }

    /**
     * Resume an import whose worker stopped before finishing.
     *
     * The queue holds the only copy of the work, so a worker that exits (or was
     * never subscribed to the queue) leaves the record at "importing" with rows
     * still staged. Re-queueing from the last applied row is safe and resumes
     * exactly where it stopped.
     */
    public function retry(string $uuid): JsonResponse
    {
        $import = $this->findImport($uuid);
        $this->authorize('update', $import);

        if ($import->status !== ProductImportStatus::IMPORTING) {
            return ApiResponse::error(
                'Only an import that is still running can be retried. Upload the file again if it has already finished.',
                null,
                409,
            );
        }

        $valid = $import->rows()->where('status', ProductImportRowStatus::VALID)->count();

        if ($valid === 0) {
            // The worker applied every row but died before settling the record.
            $this->imports->finishApply($import);

            return ApiResponse::success(
                new ProductImportResource($this->withCounts($import->refresh())),
                'Nothing was left to apply; the import has been marked as finished.',
            );
        }

        $cursor = $this->imports->resumeCursor($import);

        ApplyProductImportChunkJob::dispatch($import->id, $cursor)->onQueue(ApplyProductImportChunkJob::queueName());
        // Move the staleness clock so the watchdog does not queue it again.
        $import->touch();

        return ApiResponse::success(
            new ProductImportResource($this->withCounts($import->refresh())),
            "Import re-queued: {$valid} rows remain.",
            202,
        );
    }

    public function cancel(string $uuid): JsonResponse
    {
        $import = $this->findImport($uuid);
        $this->authorize('update', $import);

        $this->imports->cancel($import);

        return ApiResponse::success(
            new ProductImportResource($this->withCounts($import->refresh())),
            'Import cancelled. Rows already applied stay applied.',
        );
    }

    /**
     * Download the rejected rows as a CSV the operator can fix and re-upload.
     */
    public function errorsCsv(string $uuid): StreamedResponse
    {
        $import = $this->findImport($uuid);
        $this->authorize('view', $import);

        $filename = 'import-errors-'.$import->uuid.'.csv';

        return response()->streamDownload(function () use ($import): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['row_number', 'external_id', 'status', 'errors']);

            $import->rows()
                ->whereIn('status', [ProductImportRowStatus::INVALID, ProductImportRowStatus::FAILED])
                ->orderBy('row_number')
                ->chunk(500, function ($rows) use ($handle): void {
                    foreach ($rows as $row) {
                        $errors = collect((array) $row->errors)
                            ->map(fn ($messages, $field): string => $field.': '.(is_array($messages) ? implode(' ', $messages) : $messages))
                            ->implode(' | ');

                        fputcsv($handle, [
                            $row->row_number,
                            self::csvSafe((string) $row->external_id),
                            $row->status,
                            self::csvSafe($errors),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function findImport(string $uuid): ProductImport
    {
        return ProductImport::query()->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Row counts the client needs for the progress/report UI. Loaded per request
     * rather than stored, so they can never drift from the rows themselves.
     *
     * @return array<string, \Closure>
     */
    private function rowCounts(): array
    {
        $status = fn (string $value) => fn ($query) => $query->where('status', $value);

        return [
            'rows as valid_rows_count' => $status(ProductImportRowStatus::VALID),
            'rows as invalid_rows_count' => $status(ProductImportRowStatus::INVALID),
            'rows as imported_rows_count' => $status(ProductImportRowStatus::IMPORTED),
        ];
    }

    private function withCounts(ProductImport $import): ProductImport
    {
        return $import->loadCount($this->rowCounts());
    }

    /**
     * Spreadsheet applications execute a cell that starts with `=`, `+`, `-` or
     * `@`. Imported values are untrusted input, so neutralise them.
     */
    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
