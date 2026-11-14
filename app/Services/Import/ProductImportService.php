<?php

namespace App\Services\Import;

use App\Actions\Product\ProductCreateAction;
use App\Actions\Product\ProductUpdateAction;
use App\Actions\Variant\VariantCreateAction;
use App\Enums\ProductImportKind;
use App\Enums\ProductImportRowStatus;
use App\Enums\ProductImportStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Dam;
use App\Models\Definition;
use App\Models\Highlight;
use App\Models\Inventory;
use App\Models\Manufacturer;
use App\Models\Option;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\ProductShipping;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\Variant;
use App\Models\Warehouse;
use App\Rules\Gtin;
use App\Support\Html\RichText;
use App\Support\Import\RowValidationException;
use App\Support\Import\TabularReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Parses a product file into staged rows, validates each row, then applies the
 * valid rows to the catalogue in chunks.
 *
 * Parsing and applying are deliberately separate: the file is read once, the
 * operator (or a dry run) sees every error first, and the apply step can resume
 * without touching the source file again.
 */
class ProductImportService
{
    private const PARSE_BATCH = 500;

    private const APPLY_CHUNK = 250;

    /**
     * A single file is capped so parsing stays inside the request timeout and
     * memory limit. Larger catalogues are uploaded as several files.
     */
    public const MAX_ROWS = 50000;

    /** @var array<string, int|null> */
    private array $categoryCache = [];

    /** @var array<string, int|null> */
    private array $brandCache = [];

    /** @var array<string, int|null> */
    private array $manufacturerCache = [];

    /** @var array<string, int> */
    private array $tagCache = [];

    /** @var array<string, int|null> */
    private array $unitCache = [];

    /** @var array<string, int|null> */
    private array $warehouseCache = [];

    public function __construct(
        protected ProductCreateAction $createAction,
        protected ProductUpdateAction $updateAction,
        protected VariantCreateAction $createVariantAction,
        protected ProductImageIngestor $imageIngestor,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createRecord(array $attributes): ProductImport
    {
        return ProductImport::query()->create(array_merge([
            'status' => ProductImportStatus::PENDING,
            'disk' => 'local',
            'format' => TabularReader::format((string) data_get($attributes, 'path', '')),
        ], $attributes));
    }

    /**
     * Resolve the tenant/organization an import belongs to.
     *
     * An explicit pair is validated against the caller's membership (admins may
     * target any organization). When the pair is omitted it is taken from the
     * caller's primary membership — the management portals have no organization
     * picker — and an admin without a membership must still be explicit.
     *
     * @return array{tenant_id: int, organization_id: int}
     */
    public function resolveTarget(?int $tenantId, ?int $organizationId): array
    {
        $user = auth()->user();

        if ($tenantId === null || $organizationId === null) {
            $membership = $user?->primaryMembership();

            abort_if(
                $membership === null,
                $user?->isAdmin() ? 422 : 403,
                $user?->isAdmin()
                    ? 'tenant_id and organization_id are required.'
                    : 'No active organization is associated with your account.',
            );

            $tenantId ??= (int) $membership->tenant_id;
            $organizationId ??= (int) $membership->organization_id;
        }

        abort_unless(
            $user?->isAdmin()
                || $user?->activeMemberships()
                    ->where('tenant_id', $tenantId)
                    ->where('organization_id', $organizationId)
                    ->exists(),
            403,
            'You do not have access to this organization.',
        );

        return ['tenant_id' => (int) $tenantId, 'organization_id' => (int) $organizationId];
    }

    /**
     * Read the file into `product_import_rows`, validating each row.
     */
    public function parse(ProductImport $import): void
    {
        $import->update([
            'status' => ProductImportStatus::PARSING,
            'started_at' => $import->started_at ?? now(),
            'error_message' => null,
            // Re-validating the same record replaces its staged rows, so the
            // counters from a previous run must not leak into this one.
            'processed_rows' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'finished_at' => null,
        ]);

        $import->rows()->delete();
        $this->resetCaches();

        $options = $this->options($import);
        $path = $this->resolvePath($import);
        $kind = $this->kind($import);

        $total = 0;
        $invalid = 0;
        $batch = [];
        $now = now();

        try {
            foreach ((new TabularReader($path, data_get($options, 'sheet')))->rows() as $row) {
                $total++;

                if ($total > self::MAX_ROWS) {
                    throw new RuntimeException('The file has more than '.number_format(self::MAX_ROWS).' rows. Split it into smaller files.');
                }

                try {
                    $normalized = match ($kind) {
                        ProductImportKind::IMAGES => $this->normalizeImageRow($row, $import),
                        ProductImportKind::VARIANTS => $this->normalizeVariantRow($row, $import),
                        default => $this->normalize($row, $import, $options),
                    };
                    $status = ProductImportRowStatus::VALID;
                    $errors = null;
                } catch (RowValidationException $exception) {
                    $normalized = $exception->payload;
                    $status = ProductImportRowStatus::INVALID;
                    $errors = $exception->errors;
                    $invalid++;
                }

                $batch[] = [
                    'import_id' => $import->id,
                    'row_number' => (int) data_get($row, '__row', $total),
                    'external_id' => $this->externalId($row),
                    'raw' => json_encode($row, JSON_UNESCAPED_UNICODE),
                    'normalized' => $normalized === null
                        ? null
                        : json_encode($normalized, JSON_UNESCAPED_UNICODE),
                    'status' => $status,
                    'errors' => $errors === null ? null : json_encode($errors, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= self::PARSE_BATCH) {
                    ProductImportRow::query()->insert($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                ProductImportRow::query()->insert($batch);
            }
        } catch (Throwable $exception) {
            $import->update([
                'status' => ProductImportStatus::FAILED,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }

        $import->update([
            'status' => ProductImportStatus::VALIDATED,
            'total_rows' => $total,
            'processed_rows' => $total,
            'failed_count' => $invalid,
            'finished_at' => null,
        ]);
    }

    /**
     * Apply every valid staged row. Invalid rows stay untouched for the report.
     *
     * Used by the CLI. The HTTP flow applies one `applyChunk()` per queue job so
     * a large file never runs inside a single request or worker timeout.
     */
    public function apply(ProductImport $import): void
    {
        $this->beginApply($import);

        $cursor = 0;

        do {
            $chunk = $this->applyChunk($import, $cursor);
            $cursor = $chunk['next_row_id'];
        } while ($chunk['has_more']);

        $this->finishApply($import);
    }

    /**
     * Move a parsed import into the applying state.
     */
    public function beginApply(ProductImport $import): void
    {
        $import->update([
            'status' => ProductImportStatus::IMPORTING,
            'started_at' => $import->started_at ?? now(),
            // Validation already consumed the staging progress; application
            // starts its own counter so the bar tracks rows actually written.
            'processed_rows' => 0,
            'finished_at' => null,
        ]);
    }

    /**
     * Apply a bounded batch of valid rows, resuming after `$afterRowId`.
     *
     * Each row runs in its own transaction so one bad row cannot roll back its
     * neighbours; failures are recorded on the row and counted, never thrown.
     *
     * @return array{processed: int, next_row_id: int, has_more: bool}
     */
    public function applyChunk(ProductImport $import, int $afterRowId, int $limit = self::APPLY_CHUNK): array
    {
        $rows = ProductImportRow::query()
            ->where('import_id', $import->id)
            ->where('status', ProductImportRowStatus::VALID)
            ->where('id', '>', $afterRowId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $created = (int) $import->created_count;
        $updated = (int) $import->updated_count;
        $skipped = (int) $import->skipped_count;
        $failed = (int) $import->failed_count;
        $processed = (int) $import->processed_rows;
        $lastId = $afterRowId;

        foreach ($rows as $row) {
            $lastId = (int) $row->id;
            $processed++;

            try {
                $outcome = DB::transaction(fn (): array => $this->applyRow($import, $row));
            } catch (Throwable $exception) {
                $row->update([
                    'status' => ProductImportRowStatus::FAILED,
                    'errors' => ['row' => $exception->getMessage()],
                ]);
                $failed++;

                continue;
            }

            $row->update([
                'status' => $outcome['status'],
                'product_id' => $outcome['product_id'],
                'errors' => $outcome['errors'],
                'warnings' => $outcome['warnings'] ?? null,
            ]);

            match ($outcome['result']) {
                'created' => $created++,
                'updated' => $updated++,
                'skipped' => $skipped++,
                default => null,
            };

            if ($outcome['result'] === 'failed') {
                $failed++;
            }
        }

        $import->update([
            'processed_rows' => $processed,
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'failed_count' => $failed,
        ]);

        $hasMore = $rows->count() === $limit
            && ProductImportRow::query()
                ->where('import_id', $import->id)
                ->where('status', ProductImportRowStatus::VALID)
                ->where('id', '>', $lastId)
                ->exists();

        return [
            'processed' => $rows->count(),
            'next_row_id' => $lastId,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Settle an import once every chunk has run.
     */
    public function finishApply(ProductImport $import): void
    {
        if ($import->status === ProductImportStatus::CANCELLED) {
            return;
        }

        $import->update([
            'status' => (int) $import->failed_count > 0
                ? ProductImportStatus::COMPLETED_WITH_ERRORS
                : ProductImportStatus::COMPLETED,
            'finished_at' => now(),
        ]);
    }

    /**
     * Row id after which an interrupted apply should resume.
     *
     * Rows are applied in ascending id order, and an applied row leaves the
     * `valid` state, so the highest attempted row is a precise cursor: every
     * row above it is untouched, every row below it is settled. That lets a
     * recovered import continue where its dead worker stopped instead of
     * reprocessing the whole file.
     */
    public function resumeCursor(ProductImport $import): int
    {
        return (int) ProductImportRow::query()
            ->where('import_id', $import->id)
            ->whereIn('status', [
                ProductImportRowStatus::IMPORTED,
                ProductImportRowStatus::SKIPPED,
                ProductImportRowStatus::FAILED,
            ])
            ->max('id');
    }

    /**
     * Stop an import that has not settled yet. Rows already applied stay applied.
     */
    public function cancel(ProductImport $import): void
    {
        if (in_array($import->status, [
            ProductImportStatus::COMPLETED,
            ProductImportStatus::COMPLETED_WITH_ERRORS,
            ProductImportStatus::FAILED,
            ProductImportStatus::CANCELLED,
        ], true)) {
            return;
        }

        $import->update([
            'status' => ProductImportStatus::CANCELLED,
            'finished_at' => now(),
        ]);
    }

    /**
     * Mark a parsed (dry-run) import as finished without applying anything.
     */
    public function completeDryRun(ProductImport $import): void
    {
        $import->update([
            'status' => ProductImportStatus::COMPLETED,
            'processed_rows' => (int) $import->total_rows,
            'finished_at' => now(),
        ]);
    }

    /**
     * @return array{status: string, product_id: int|null, result: string, errors: array<string, string>|null, warnings?: list<string>|null}
     */
    private function applyRow(ProductImport $import, ProductImportRow $row): array
    {
        return match ($this->kind($import)) {
            ProductImportKind::IMAGES => $this->applyImageRow($import, $row),
            ProductImportKind::VARIANTS => $this->applyVariantRow($import, $row),
            default => $this->applyProductRow($import, $row),
        };
    }

    /**
     * @return array{status: string, product_id: int|null, result: string, errors: array<string, string>|null, warnings?: list<string>|null}
     */
    private function applyProductRow(ProductImport $import, ProductImportRow $row): array
    {
        $payload = (array) $row->normalized;
        $action = (string) data_get($payload, 'action', 'upsert');
        $externalId = (string) data_get($payload, 'external_id');

        $product = $this->productBySellerSku($import, $externalId);

        if ($product !== null && $action === 'create_only') {
            return [
                'status' => ProductImportRowStatus::SKIPPED,
                'product_id' => $product->id,
                'result' => 'skipped',
                'errors' => ['row' => 'A product with this external_id already exists.'],
            ];
        }

        if ($product === null && $action === 'update_only') {
            return [
                'status' => ProductImportRowStatus::SKIPPED,
                'product_id' => null,
                'result' => 'skipped',
                'errors' => ['row' => 'No product exists with this external_id.'],
            ];
        }

        if ($product !== null && $product->trashed()) {
            $product->restore();
        }

        if ($product !== null) {
            $product = $this->updateAction->handle($product, (array) data_get($payload, 'product', []));
            $result = 'updated';
        } else {
            $product = $this->createAction->handle((array) data_get($payload, 'product', []));
            $result = 'created';
        }

        $this->syncInventory($product, (array) data_get($payload, 'inventory', []));
        $this->syncShipping($product, (array) data_get($payload, 'shipping', []));
        $this->syncHighlights($import, $product, (array) data_get($payload, 'product.highlights', []));

        $warnings = array_merge(
            (array) data_get($payload, 'warnings', []),
            $this->syncImages($import, $product, (array) data_get($payload, 'images', [])),
        );

        return [
            'status' => ProductImportRowStatus::IMPORTED,
            'product_id' => $product->id,
            'result' => $result,
            'errors' => null,
            'warnings' => $warnings,
        ];
    }

    /**
     * Attach the row's remote images to the product it created.
     *
     * Failures are warnings, never row errors: a product with a dead image link
     * is still a valid product, and blocking it would make a whole file fail
     * because one CDN URL rotted.
     *
     * @param  list<string>  $urls
     * @return list<string>
     */
    private function syncImages(ProductImport $import, Product $product, array $urls): array
    {
        if ($urls === [] || ! $this->downloadsImages($import)) {
            return [];
        }

        return $this->imageIngestor->ingest($product, $urls, $import->user_id)['warnings'];
    }

    /**
     * @return array{status: string, product_id: int|null, result: string, errors: array<string, string>|null, warnings?: list<string>|null}
     */
    private function applyImageRow(ProductImport $import, ProductImportRow $row): array
    {
        $payload = (array) $row->normalized;
        $product = $this->productBySellerSku($import, (string) data_get($payload, 'external_id'));

        if ($product === null) {
            return [
                'status' => ProductImportRowStatus::FAILED,
                'product_id' => null,
                'result' => 'failed',
                'errors' => ['external_id' => 'No product exists with this external_id.'],
            ];
        }

        $image = (array) data_get($payload, 'image', []);

        try {
            $dam = $this->imageIngestor->ingestOne(
                $product,
                (string) data_get($image, 'url'),
                $import->user_id,
                data_get($image, 'collection'),
                data_get($image, 'sort_order') === null ? null : (int) data_get($image, 'sort_order'),
                data_get($image, 'alt'),
            );
        } catch (Throwable $exception) {
            return [
                'status' => ProductImportRowStatus::FAILED,
                'product_id' => $product->id,
                'result' => 'failed',
                'errors' => ['image_url' => $exception->getMessage()],
            ];
        }

        if ($dam === null) {
            return [
                'status' => ProductImportRowStatus::SKIPPED,
                'product_id' => $product->id,
                'result' => 'skipped',
                'errors' => ['row' => 'This image is already attached to the product.'],
            ];
        }

        return [
            'status' => ProductImportRowStatus::IMPORTED,
            'product_id' => $product->id,
            'result' => 'updated',
            'errors' => null,
        ];
    }

    /**
     * @return array{status: string, product_id: int|null, result: string, errors: array<string, string>|null, warnings?: list<string>|null}
     */
    private function applyVariantRow(ProductImport $import, ProductImportRow $row): array
    {
        $payload = (array) $row->normalized;
        $externalId = (string) data_get($payload, 'external_id');
        $parent = $this->productBySellerSku($import, (string) data_get($payload, 'parent_external_id'));

        if ($parent === null) {
            return [
                'status' => ProductImportRowStatus::FAILED,
                'product_id' => null,
                'result' => 'failed',
                'errors' => ['parent_external_id' => 'No product exists with this external_id.'],
            ];
        }

        $data = (array) data_get($payload, 'variant', []);
        $variant = Variant::withTrashed()
            ->where('tenant_id', $import->tenant_id)
            ->where('seller_sku', $externalId)
            ->first();

        if ($variant !== null && $variant->trashed()) {
            $variant->restore();
        }

        if ($variant !== null && (string) data_get($payload, 'action', 'upsert') === 'create_only') {
            return [
                'status' => ProductImportRowStatus::SKIPPED,
                'product_id' => $parent->id,
                'result' => 'skipped',
                'errors' => ['row' => 'A variant with this external_id already exists.'],
            ];
        }

        if ($variant === null && (string) data_get($payload, 'action', 'upsert') === 'update_only') {
            return [
                'status' => ProductImportRowStatus::SKIPPED,
                'product_id' => $parent->id,
                'result' => 'skipped',
                'errors' => ['row' => 'No variant exists with this external_id.'],
            ];
        }

        if ($variant !== null) {
            // `sku` is generated once and must survive re-imports, like a product's.
            $variant->fill(array_diff_key($data, ['sku' => true, 'slug' => true]))->save();
            $result = 'updated';
        } else {
            $data['product_id'] = $parent->id;
            $data['seller_sku'] = $externalId;
            $data['is_default'] = $data['is_default']
                ?? $parent->variants()->where('is_default', true)->doesntExist();

            $variant = $this->createVariantAction->handle($data);
            $result = 'created';
        }

        $this->syncInventory(
            $variant,
            (array) data_get($payload, 'inventory', []),
        );

        return [
            'status' => ProductImportRowStatus::IMPORTED,
            'product_id' => $parent->id,
            'result' => $result,
            'errors' => null,
        ];
    }

    private function productBySellerSku(ProductImport $import, string $sellerSku): ?Product
    {
        if ($sellerSku === '') {
            return null;
        }

        return Product::withTrashed()
            ->where('tenant_id', $import->tenant_id)
            ->where('seller_sku', $sellerSku)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $inventory
     */
    private function syncInventory(Product|Variant $stockable, array $inventory): void
    {
        if ($inventory === [] || data_get($inventory, 'quantity') === null) {
            return;
        }

        Inventory::query()->updateOrCreate(
            [
                'warehouse_id' => (int) data_get($inventory, 'warehouse_id'),
                'stockable_type' => $stockable->getMorphClass(),
                'stockable_id' => $stockable->id,
            ],
            [
                'tenant_id' => (int) $stockable->tenant_id,
                'sku' => (string) ($stockable->seller_sku ?? $stockable->sku),
                'quantity' => (float) data_get($inventory, 'quantity'),
                'low_stock_threshold' => data_get($inventory, 'low_stock_threshold'),
                'track_inventory' => true,
                'is_active' => true,
            ],
        );
    }

    /**
     * Attach the row's highlights, reusing an existing record with the same
     * tenant, heading and body so re-importing a file stays idempotent.
     *
     * A blank cell leaves the product's current highlights untouched; a filled
     * cell is the source of truth, so highlights dropped from the file are
     * detached.
     *
     * @param  array<int, array{heading: string|null, body: string}>  $items
     */
    private function syncHighlights(ProductImport $import, Product $product, array $items): void
    {
        if ($items === []) {
            return;
        }

        $sync = [];

        foreach (array_values($items) as $index => $item) {
            $heading = $item['heading'];
            $body = $item['body'];

            $highlight = Highlight::query()
                ->where('tenant_id', $import->tenant_id)
                ->where('body', $body)
                ->when(
                    $heading === null,
                    fn ($query) => $query->whereNull('heading'),
                    fn ($query) => $query->where('heading', $heading),
                )
                ->first();

            $highlight ??= Highlight::query()->create([
                'tenant_id' => $import->tenant_id,
                'heading' => $heading,
                'body' => $body,
                'is_active' => true,
                'sort_order' => $index,
            ]);

            $sync[$highlight->id] = ['sort_order' => $index];
        }

        $product->highlights()->sync($sync);
    }

    /**
     * `Heading=Body` entries separated by a pipe; a bare entry has no heading.
     *
     * @return array<int, array{heading: string|null, body: string}>
     */
    private function parseHighlights(string $value): array
    {
        $highlights = [];

        foreach ($this->pillars($value) as $entry) {
            $parts = explode('=', $entry, 2);
            $heading = count($parts) === 2 ? trim($parts[0]) : '';
            $body = trim(count($parts) === 2 ? $parts[1] : $parts[0]);

            if ($body === '') {
                continue;
            }

            $highlights[] = [
                'heading' => $heading !== '' ? $heading : null,
                'body' => $body,
            ];
        }

        return $highlights;
    }

    /**
     * @param  array<string, mixed>  $shipping
     */
    private function syncShipping(Product $product, array $shipping): void
    {
        if ($shipping === []) {
            return;
        }

        ProductShipping::query()->updateOrCreate(
            ['product_id' => $product->id],
            $shipping,
        );
    }

    /**
     * One image row: attach a remote image to an existing product.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function normalizeImageRow(array $row, ProductImport $import): array
    {
        $externalId = $this->externalId($row);

        if ($externalId === '') {
            throw new RowValidationException(['external_id' => 'external_id is required.']);
        }

        $url = trim((string) ($row['image_url'] ?? ''));

        if ($url === '') {
            throw new RowValidationException(['image_url' => 'image_url is required.']);
        }

        if (! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new RowValidationException(['image_url' => 'image_url must be an http(s) URL.']);
        }

        if ($this->productBySellerSku($import, $externalId) === null) {
            throw new RowValidationException([
                'external_id' => "No product exists with external_id '{$externalId}'.",
            ]);
        }

        return [
            'external_id' => $externalId,
            'action' => 'upsert',
            'image' => [
                'url' => $url,
                'alt' => trim((string) ($row['alt'] ?? '')) ?: null,
                'sort_order' => $this->number($row['sort_order'] ?? null, 'sort_order'),
                'collection' => $this->boolean($row['is_featured'] ?? null, false)
                    ? Dam::COLLECTION_FEATURED
                    : Dam::COLLECTION_GALLERY,
            ],
        ];
    }

    /**
     * One variant row: a child of an existing product, keyed by its own SKU.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function normalizeVariantRow(array $row, ProductImport $import): array
    {
        $action = strtolower(trim((string) ($row['action'] ?? ''))) ?: 'upsert';

        if (! in_array($action, ['upsert', 'create_only', 'update_only'], true)) {
            throw new RowValidationException(['action' => 'Use upsert, create_only or update_only.']);
        }

        $externalId = $this->externalId($row);

        if ($externalId === '') {
            throw new RowValidationException(['external_id' => 'external_id is required (it is the variant upsert key).']);
        }

        $parentSku = trim((string) ($row['parent_external_id'] ?? ''));

        if ($parentSku === '') {
            throw new RowValidationException(['parent_external_id' => 'parent_external_id is required.']);
        }

        $parent = $this->productBySellerSku($import, $parentSku);

        if ($parent === null) {
            throw new RowValidationException([
                'parent_external_id' => "No product exists with external_id '{$parentSku}'.",
            ]);
        }

        $configuration = [];

        foreach ([1, 2, 3] as $index) {
            $name = trim((string) ($row["option_{$index}_name"] ?? ''));
            $value = trim((string) ($row["option_{$index}_value"] ?? ''));

            if ($name === '') {
                continue;
            }

            if ($value === '') {
                throw new RowValidationException([
                    "option_{$index}_value" => 'A value is required when the option name is set.',
                ]);
            }

            // Prefer the definition's code so the variant configuration matches
            // what the catalogue UI writes; fall back to a slug for free text.
            $definition = Definition::query()
                ->where('code', Str::slug($name))
                ->orWhere('name', $name)
                ->first();

            $configuration[$definition?->code ?? Str::slug($name)] = $value;
        }

        if ($configuration === []) {
            throw new RowValidationException(['option_1_name' => 'At least one option (name and value) is required.']);
        }

        $gtin = trim((string) ($row['gtin'] ?? ''));

        if ($gtin !== '' && ! Gtin::isValid($gtin)) {
            throw new RowValidationException(['gtin' => 'The GTIN is not valid (length or check digit).']);
        }

        $price = $this->number($row['price'] ?? null, 'price');
        $salePrice = $this->number($row['sale_price'] ?? null, 'sale_price');
        $costPrice = $this->number($row['cost_price'] ?? null, 'cost_price');
        $quantity = $this->number($row['stock_qty'] ?? null, 'stock_qty');
        $lowStock = $this->number($row['low_stock_threshold'] ?? null, 'low_stock_threshold');

        $warehouseId = $this->resolveWarehouse(
            (string) ($row['warehouse_code'] ?? data_get($this->options($import), 'warehouse', '')),
            $import,
        );

        if ($quantity !== null && $warehouseId === null) {
            throw new RowValidationException([
                'warehouse_code' => 'A warehouse is required when stock_qty is provided.',
            ]);
        }

        $status = strtolower(trim((string) ($row['status'] ?? '')))
            ?: ($parent->status === 'active' ? 'active' : 'draft');

        return [
            'external_id' => $externalId,
            'parent_external_id' => $parentSku,
            'action' => $action,
            'variant' => [
                'name' => trim((string) ($row['name'] ?? ''))
                    ?: $parent->name.' - '.implode(' / ', array_values($configuration)),
                'seller_sku' => $externalId,
                'configuration' => $configuration,
                'status' => $status,
                'is_active' => $this->boolean($row['is_active'] ?? null, true),
                'metadata' => array_filter([
                    'price' => $price,
                    'sale_price' => $salePrice,
                    'cost_price' => $costPrice,
                    'currency' => strtoupper(trim((string) ($row['currency'] ?? '')))
                        ?: (string) data_get($parent->metadata, 'currency', 'PKR'),
                    'gtin' => $gtin !== '' ? $gtin : null,
                    'barcode' => trim((string) ($row['barcode'] ?? '')) ?: null,
                ], fn ($value): bool => $value !== null && $value !== ''),
            ],
            'inventory' => $quantity === null ? [] : [
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'low_stock_threshold' => $lowStock,
            ],
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function normalize(array $row, ProductImport $import, array $options): array
    {
        $action = strtolower(trim((string) ($row['action'] ?? ''))) ?: 'upsert';

        if (! in_array($action, ['upsert', 'create_only', 'update_only'], true)) {
            throw new RowValidationException([
                'action' => 'Use upsert, create_only or update_only.',
            ]);
        }

        $externalId = $this->externalId($row);

        if ($externalId === '') {
            throw new RowValidationException(['external_id' => 'external_id is required.']);
        }

        $name = trim((string) ($row['name'] ?? ''));

        if ($name === '') {
            throw new RowValidationException(['name' => 'name is required.']);
        }

        $categoryIds = $this->resolveCategoryPath(
            (string) ($row['category_path'] ?? ''),
            $import,
            (bool) data_get($options, 'create_missing', false),
        );

        $price = $this->number($row['price'] ?? null, 'price', true);
        $salePrice = $this->number($row['sale_price'] ?? null, 'sale_price');
        $costPrice = $this->number($row['cost_price'] ?? null, 'cost_price');
        $quantity = $this->number($row['stock_qty'] ?? $row['quantity'] ?? null, 'stock_qty');
        $lowStock = $this->number($row['low_stock_threshold'] ?? null, 'low_stock_threshold');

        $currency = strtoupper(trim((string) ($row['currency'] ?? ''))) ?: 'PKR';

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new RowValidationException(['currency' => 'Use a three-letter ISO currency code.']);
        }

        $status = strtolower(trim((string) ($row['status'] ?? ''))) ?: 'draft';

        if (! in_array($status, ['draft', 'active'], true)) {
            throw new RowValidationException(['status' => 'Use draft or active.']);
        }

        $gtin = trim((string) ($row['gtin'] ?? ''));

        if ($gtin !== '' && ! Gtin::isValid($gtin)) {
            throw new RowValidationException(['gtin' => 'The GTIN is not valid (length or check digit).']);
        }

        $warehouseId = $this->resolveWarehouse(
            (string) ($row['warehouse_code'] ?? data_get($options, 'warehouse', '')),
            $import,
        );

        if ($quantity !== null && $warehouseId === null) {
            throw new RowValidationException([
                'warehouse_code' => 'A warehouse is required when stock_qty is provided.',
            ]);
        }

        $unitCode = trim((string) ($row['unit'] ?? ''));
        $unitId = null;
        $warnings = [];

        if ($unitCode !== '') {
            try {
                $unitId = $this->resolveUnit($unitCode);
            } catch (RowValidationException) {
                // The unit is optional metadata: a missing measurement catalogue
                // must not throw away an otherwise valid product.
                $warnings[] = "Unit '{$unitCode}' does not exist, so it was not set on the product.";
            }
        }

        $metadata = array_filter([
            'price' => $price,
            'sale_price' => $salePrice,
            'currency' => $currency,
            'unit_id' => $unitId,
            'specs' => $this->parseSpecs((string) ($row['specs'] ?? '')),
            'seo_title' => trim((string) ($row['seo_title'] ?? '')) ?: null,
            'seo_description' => trim((string) ($row['seo_description'] ?? '')) ?: null,
        ], fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        return [
            'external_id' => $externalId,
            'action' => $action,
            'warnings' => $warnings,
            'product' => [
                'tenant_id' => $import->tenant_id,
                'organization_id' => $import->organization_id,
                'name' => $name,
                'slug' => trim((string) ($row['slug'] ?? '')) ?: null,
                'seller_sku' => $externalId,
                'part_number' => trim((string) ($row['part_number'] ?? '')) ?: null,
                'model_number' => trim((string) ($row['model_number'] ?? '')) ?: null,
                'gtin' => $gtin !== '' ? $gtin : null,
                'barcode' => trim((string) ($row['barcode'] ?? '')) ?: null,
                'hs_code' => trim((string) ($row['hs_code'] ?? '')) ?: null,
                'unspsc' => trim((string) ($row['unspsc'] ?? '')) ?: null,
                'ntn' => trim((string) ($row['ntn'] ?? '')) ?: null,
                'cost_price' => $costPrice,
                'status' => $status,
                'is_active' => $this->boolean($row['is_active'] ?? null, $status === 'active'),
                'summary' => trim((string) ($row['summary'] ?? '')) ?: null,
                'description' => RichText::sanitize((string) ($row['description_html'] ?? $row['description'] ?? '')),
                'highlights' => $this->parseHighlights((string) ($row['highlights'] ?? '')),
                'category_ids' => $categoryIds,
                'features' => $this->pillars((string) ($row['features'] ?? '')),
                'tag_ids' => $this->resolveTags((string) ($row['tags'] ?? '')),
                'brand_ids' => $this->oneOrNone($this->resolveBrand(
                    (string) ($row['brand'] ?? ''),
                    $import,
                    (bool) data_get($options, 'create_missing', false),
                )),
                'manufacturer_ids' => $this->oneOrNone($this->resolveManufacturer(
                    (string) ($row['manufacturer'] ?? ''),
                    $import,
                    (bool) data_get($options, 'create_missing', false),
                )),
                'channel_codes' => $this->pillars((string) ($row['channel_codes'] ?? 'storefront')),
                'metadata' => $metadata,
            ],
            'inventory' => $quantity === null ? [] : [
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
                'low_stock_threshold' => $lowStock,
            ],
            'shipping' => $this->shipping($row),
            'images' => $this->pillars((string) ($row['image_urls'] ?? '')),
            'videos' => $this->pillars((string) ($row['video_urls'] ?? '')),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function resolveCategoryPath(string $path, ProductImport $import, bool $createMissing): array
    {
        $parts = array_values(array_filter(
            array_map('trim', preg_split('/[>\/]+/', $path) ?: []),
            fn (string $part): bool => $part !== '',
        ));

        if ($parts === []) {
            throw new RowValidationException(['category_path' => 'category_path is required.']);
        }

        $ids = [];
        $parentId = null;

        foreach ($parts as $part) {
            $slug = Str::slug($part);
            $cacheKey = ($parentId ?? 'root').':'.$slug;

            if (! empty($this->categoryCache[$cacheKey])) {
                $parentId = (int) $this->categoryCache[$cacheKey];
                $ids[] = $parentId;

                continue;
            }

            $category = Category::query()
                ->where(fn ($query) => $query->where('slug', $slug)->orWhere('name', $part))
                ->when(
                    $parentId === null,
                    fn ($query) => $query->whereNull('parent_id'),
                    fn ($query) => $query->where('parent_id', $parentId),
                )
                ->first();

            if ($category === null) {
                if (! $createMissing) {
                    throw new RowValidationException([
                        'category_path' => "Category '{$part}' does not exist.",
                    ]);
                }

                $category = Category::query()->create([
                    'name' => $part,
                    'slug' => $slug,
                    'parent_id' => $parentId,
                    'is_active' => true,
                ]);
            }

            $this->categoryCache[$cacheKey] = $category->id;
            $parentId = $category->id;
            $ids[] = $category->id;
        }

        return array_values(array_unique($ids));
    }

    private function resolveBrand(string $name, ProductImport $import, bool $createMissing): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name);

        if (array_key_exists($slug, $this->brandCache)) {
            return $this->brandCache[$slug];
        }

        $brand = Brand::query()->where('slug', $slug)->first();

        if ($brand === null && $createMissing) {
            $brand = Brand::query()->create([
                'name' => $name,
                'slug' => $slug,
                'tenant_id' => $import->tenant_id,
                'organization_id' => $import->organization_id,
                'is_approved' => true,
            ]);
        }

        if ($brand === null) {
            throw new RowValidationException(['brand' => "Brand '{$name}' does not exist."]);
        }

        return $this->brandCache[$slug] = $brand->id;
    }

    private function resolveManufacturer(string $name, ProductImport $import, bool $createMissing): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name);

        if (array_key_exists($slug, $this->manufacturerCache)) {
            return $this->manufacturerCache[$slug];
        }

        $manufacturer = Manufacturer::query()->where('slug', $slug)->first();

        if ($manufacturer === null && $createMissing) {
            $manufacturer = Manufacturer::query()->create([
                'name' => $name,
                'slug' => $slug,
                'tenant_id' => $import->tenant_id,
                'organization_id' => $import->organization_id,
                'is_approved' => true,
            ]);
        }

        if ($manufacturer === null) {
            throw new RowValidationException(['manufacturer' => "Manufacturer '{$name}' does not exist."]);
        }

        return $this->manufacturerCache[$slug] = $manufacturer->id;
    }

    /**
     * @return array<int, int>
     */
    private function resolveTags(string $value): array
    {
        $ids = [];

        foreach ($this->pillars($value) as $name) {
            $slug = Str::slug($name);

            if (! isset($this->tagCache[$slug])) {
                $tag = Tag::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'is_active' => true],
                );

                $this->tagCache[$slug] = (int) $tag->id;
            }

            $ids[] = $this->tagCache[$slug];
        }

        return array_values(array_unique($ids));
    }

    private function resolveUnit(string $code): ?int
    {
        $code = strtolower(trim($code));

        if ($code === '') {
            return null;
        }

        if (array_key_exists($code, $this->unitCache)) {
            return $this->unitCache[$code];
        }

        $unitId = Unit::query()->where('code', $code)->value('id');

        if ($unitId === null) {
            throw new RowValidationException(['unit' => "Unit '{$code}' does not exist."]);
        }

        return $this->unitCache[$code] = (int) $unitId;
    }

    private function resolveWarehouse(string $code, ProductImport $import): ?int
    {
        $code = trim($code);

        if (array_key_exists($code, $this->warehouseCache)) {
            return $this->warehouseCache[$code];
        }

        $warehouse = Warehouse::query()
            ->where('tenant_id', $import->tenant_id)
            ->where('is_active', true)
            ->when($code !== '', fn ($query) => $query->where(
                fn ($inner) => $inner->where('code', $code)->orWhere('slug', $code),
            ))
            ->orderBy('id')
            ->first();

        if ($warehouse === null && $code !== '') {
            throw new RowValidationException(['warehouse_code' => "Warehouse '{$code}' does not exist."]);
        }

        return $this->warehouseCache[$code] = $warehouse?->id;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function shipping(array $row): array
    {
        $weight = $this->number($row['weight_kg'] ?? null, 'weight_kg');
        $length = $this->number($row['length_cm'] ?? null, 'length_cm');
        $width = $this->number($row['width_cm'] ?? null, 'width_cm');
        $height = $this->number($row['height_cm'] ?? null, 'height_cm');

        if ($weight === null && $length === null && $width === null && $height === null
            && blank($row['ships_alone'] ?? null)
            && blank($row['is_hazmat'] ?? null)
            && blank($row['packaging_notes'] ?? null)
            && blank($row['whats_included'] ?? null)) {
            return [];
        }

        $massId = Unit::query()
            ->where('code', 'kg')
            ->whereHas('measurement', fn ($query) => $query->where('code', 'mass'))
            ->value('id');
        $lengthId = Unit::query()
            ->where('code', 'cm')
            ->whereHas('measurement', fn ($query) => $query->where('code', 'length'))
            ->value('id');

        $data = [];

        if ($weight !== null && $massId !== null) {
            $data['gross_weight'] = $weight;
            $data['weight_unit_id'] = (int) $massId;
        }

        if ($length !== null && $lengthId !== null) {
            $data['length'] = $length;
            $data['width'] = $width;
            $data['height'] = $height;
            $data['dimension_unit_id'] = (int) $lengthId;
        }

        $data['ships_alone'] = $this->boolean($row['ships_alone'] ?? null, false);
        $data['is_stackable'] = $this->boolean($row['is_stackable'] ?? null, true);
        $data['is_fragile'] = $this->boolean($row['is_fragile'] ?? null, false);
        $data['is_hazmat'] = $this->boolean($row['is_hazmat'] ?? null, false);
        $data['hazard_class_option_id'] = $this->resolveHazardClass($row['hazard_class'] ?? null);
        $data['handling_notes'] = trim((string) ($row['packaging_notes'] ?? '')) ?: null;
        $data['contents'] = $this->pillars((string) ($row['whats_included'] ?? '')) ?: null;

        return $data;
    }

    private function resolveHazardClass(?string $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $definitionId = Definition::query()->where('code', 'pkg_hazard_class')->value('id');

        if ($definitionId === null) {
            throw new RowValidationException(['hazard_class' => 'Hazard class definitions are not seeded.']);
        }

        $option = Option::query()
            ->where('definition_id', $definitionId)
            ->where(fn ($query) => $query->where('code', $value)->orWhere('name', $value))
            ->first();

        if ($option === null) {
            throw new RowValidationException(['hazard_class' => "Unknown hazard class '{$value}'."]);
        }

        return (int) $option->id;
    }

    /**
     * @return array<int, array{key: string, value: string, unit_id: null, unit_name: null}>
     */
    private function parseSpecs(string $value): array
    {
        $specs = [];

        foreach ($this->pillars($value) as $pair) {
            $parts = explode('=', $pair, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $specValue = trim($parts[1]);

            if ($key === '' || $specValue === '') {
                continue;
            }

            $specs[] = [
                'key' => $key,
                'value' => $specValue,
                'unit_id' => null,
                'unit_name' => null,
            ];
        }

        return $specs;
    }

    /**
     * @return array<int, string>
     */
    private function pillars(string $value): array
    {
        $values = array_map('trim', explode('|', $value));

        return array_values(array_filter($values, fn (string $item): bool => $item !== ''));
    }

    private function number(?string $value, string $field, bool $required = false): ?float
    {
        $value = str_replace([',', ' '], '', trim((string) $value));

        if ($value === '') {
            if ($required) {
                throw new RowValidationException([$field => "{$field} is required."]);
            }

            return null;
        }

        if (! is_numeric($value)) {
            throw new RowValidationException([$field => "{$field} must be a number."]);
        }

        return (float) $value;
    }

    private function boolean(?string $value, bool $default): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'y', 'on'], true);
    }

    /**
     * @return array<int, int>|null
     */
    private function oneOrNone(?int $id): ?array
    {
        return $id === null ? null : [$id];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function externalId(array $row): string
    {
        return trim((string) ($row['external_id'] ?? $row['seller_sku'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function options(ProductImport $import): array
    {
        return (array) ($import->options ?? []);
    }

    private function kind(ProductImport $import): string
    {
        $kind = (string) ($import->kind ?? ProductImportKind::PRODUCTS);

        return in_array($kind, ProductImportKind::ALL, true) ? $kind : ProductImportKind::PRODUCTS;
    }

    /**
     * Remote images are fetched by default; a bulk re-import of a large
     * catalogue can turn this off and attach images in a separate pass.
     */
    private function downloadsImages(ProductImport $import): bool
    {
        return (bool) data_get($this->options($import), 'download_images', true);
    }

    private function resolvePath(ProductImport $import): string
    {
        if ($import->disk === 'local') {
            return Storage::disk('local')->path($import->path);
        }

        return (string) $import->path;
    }

    private function resetCaches(): void
    {
        $this->categoryCache = [];
        $this->brandCache = [];
        $this->manufacturerCache = [];
        $this->tagCache = [];
        $this->unitCache = [];
        $this->warehouseCache = [];
    }
}
